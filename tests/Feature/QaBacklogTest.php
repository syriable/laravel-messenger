<?php

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Exceptions\InvalidAttachmentException;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Exceptions\InvalidReportException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Support\ConversationKey;
use Syriable\Messenger\Tests\Models\User;

// Issue #50/#56 — markAsUnread is a no-op without a visible received message.
it('does not mark unread when the participant only sent messages', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'hi');
    $conversation = Messenger::between($alice, $bob);

    Messenger::markAsRead($conversation, $alice);
    Messenger::markAsUnread($conversation, $alice);

    expect((int) $conversation->participantFor($alice)->fresh()->unread_count)->toBe(0);
});

it('does not mark unread after the participant cleared their history', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'hi');
    $conversation = Messenger::between($alice, $bob);

    Messenger::clear($conversation, $bob);
    Messenger::markAsUnread($conversation, $bob);

    expect((int) $conversation->participantFor($bob)->fresh()->unread_count)->toBe(0)
        ->and(Messenger::messages($conversation, $bob))->toHaveCount(0);
});

it('marks unread when there is a visible received message', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'hi');
    $conversation = Messenger::between($alice, $bob);
    Messenger::markAsRead($conversation, $bob);

    Messenger::markAsUnread($conversation, $bob);

    expect((int) $conversation->participantFor($bob)->fresh()->unread_count)->toBe(1);
});

// Issue #55 — zero-byte attachments are rejected by default.
it('rejects a zero-byte attachment by default', function () {
    Storage::fake('local');
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, [
        'attachments' => [UploadedFile::fake()->create('empty.pdf', 0, 'application/pdf')],
    ]);
})->throws(InvalidAttachmentException::class);

it('allows a zero-byte attachment when configured', function () {
    Storage::fake('local');
    config()->set('messenger.attachments.allow_empty', true);
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $message = Messenger::send($alice, $bob, [
        'attachments' => [UploadedFile::fake()->create('empty.pdf', 0, 'application/pdf')],
    ]);

    expect($message->attachments)->toHaveCount(1);
});

// Issue #52 — an over-length original filename is truncated to fit the column.
it('truncates an over-length attachment filename, preserving the extension', function () {
    Storage::fake('local');
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $longName = str_repeat('a', 300).'.pdf';
    $message = Messenger::send($alice, $bob, [
        'attachments' => [UploadedFile::fake()->create($longName, 5, 'application/pdf')],
    ]);

    $name = $message->attachments->first()->name;
    expect(mb_strlen($name))->toBeLessThanOrEqual(255)
        ->and($name)->toEndWith('.pdf');
});

// Issue #54 — recipient/sender existence check (on by default after remediation).
it('rejects a non-persisted (ghost) participant by default', function () {
    $alice = User::factory()->create();

    $ghost = new User(['name' => 'Ghost']);
    $ghost->id = 999999;

    Messenger::send($alice, $ghost, 'hi');
})->throws(InvalidParticipantException::class);

it('allows a ghost participant when verification is explicitly disabled', function () {
    config()->set('messenger.validation.verify_participants_exist', false);
    $alice = User::factory()->create();

    $ghost = new User(['name' => 'Ghost']);
    $ghost->id = 999999;

    expect(Messenger::send($alice, $ghost, 'hi')->body)->toBe('hi');
});

// Issue #57 — participant-only reporting (on by default after remediation).
it('rejects a report from a non-participant by default', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $eve = User::factory()->create();

    $message = Messenger::send($alice, $bob, 'hi');

    Messenger::report($message, $eve);
})->throws(InvalidReportException::class);

it('allows a participant to report by default', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $message = Messenger::send($alice, $bob, 'hi');

    expect(Messenger::report($message, $bob)->message_id)->toBe($message->getKey());
});

it('allows any reporter when participants_only is explicitly disabled', function () {
    config()->set('messenger.reports.participants_only', false);
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $eve = User::factory()->create();

    $message = Messenger::send($alice, $bob, 'hi');

    expect(Messenger::report($message, $eve)->message_id)->toBe($message->getKey());
});

// Issue #53 — negative/zero limits are clamped (never unbounded).
it('clamps a negative limit to a single result instead of returning everything', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'one');
    Messenger::send($alice, $bob, 'two');
    Messenger::send($alice, $bob, 'three');
    $conversation = Messenger::between($alice, $bob);

    expect(Messenger::messages($conversation, $bob, ['limit' => -5]))->toHaveCount(1)
        ->and(Messenger::messages($conversation, $bob, ['limit' => 0]))->toHaveCount(1);
});

// Issue #58 — ConversationKey is collision-proof against separator characters.
it('produces distinct conversation keys for separator-bearing identifiers', function () {
    $make = fn (string $type, string $id): MessengerParticipant => new class($type, $id) implements MessengerParticipant
    {
        public function __construct(private string $morph, private string $id) {}

        public function getMorphClass(): string
        {
            return $this->morph;
        }

        public function getKey(): string
        {
            return $this->id;
        }

        public function messengerParticipations(): MorphMany
        {
            throw new RuntimeException('not needed for key generation');
        }
    };

    // Two pairs that would collide under naive "type#id" + "|" joining.
    $keyA = ConversationKey::for($make('A', '1|B#2'), $make('C', '3'));
    $keyB = ConversationKey::for($make('A', '1'), $make('B#2|C', '3'));

    expect($keyA)->not->toBe($keyB);
});
