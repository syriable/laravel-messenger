<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Commands\InstallCommand;
use Syriable\Messenger\Exceptions\InvalidAttachmentException;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Exceptions\InvalidReportException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Models\MessageAttachment;
use Syriable\Messenger\Models\MessageReport;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Support\Limit;
use Syriable\Messenger\Tests\Models\User;

/*
 * Mass-assignment protection (#audit S1). Sensitive state columns must not be
 * mass-assignable, while the legitimate creation columns remain fillable so the
 * package actions keep working.
 */
it('does not allow mass-assigning participant state columns', function () {
    expect((new Participant)->isFillable('unread_count'))->toBeFalse()
        ->and((new Participant)->isFillable('blocked_at'))->toBeFalse()
        ->and((new Participant)->isFillable('spammed_at'))->toBeFalse()
        ->and((new Participant)->isFillable('cleared_at'))->toBeFalse()
        ->and((new Participant)->isFillable('participant_id'))->toBeTrue();
});

it('does not allow mass-assigning a forged message sender via fill', function () {
    $message = new Message;
    $message->fill([
        'body' => 'hi',
        'created_at' => '1999-01-01 00:00:00',
    ]);

    // body is fillable, created_at is not — so it cannot be back-dated via fill.
    expect($message->body)->toBe('hi')
        ->and($message->getAttribute('created_at'))->toBeNull();
});

it('still persists messages and projections correctly with fillable in place', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $message = Messenger::send($alice, $bob, 'hello');

    expect($message->body)->toBe('hello')
        ->and(Conversation::count())->toBe(1)
        ->and((int) Messenger::between($alice, $bob)->participantFor($bob)->unread_count)->toBe(1);
});

/*
 * Secure-by-default guards (#audit Critical 1 & 2).
 */
it('rejects a ghost recipient by default', function () {
    $alice = User::factory()->create();
    $ghost = new User(['name' => 'Ghost']);
    $ghost->id = 424242;

    Messenger::send($alice, $ghost, 'hi');
})->throws(InvalidParticipantException::class);

it('rejects a report from a non-participant by default', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $eve = User::factory()->create();
    $message = Messenger::send($alice, $bob, 'hi');

    Messenger::report($message, $eve);
})->throws(InvalidReportException::class);

/*
 * Attachment temporary URL helper (#audit S2).
 */
it('builds a temporary signed url for a private attachment', function () {
    Storage::fake('local');

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $message = Messenger::send($alice, $bob, [
        'attachments' => [UploadedFile::fake()->image('p.jpg')],
    ]);

    /** @var MessageAttachment $attachment */
    $attachment = $message->attachments->first();

    // The faked local disk supports temporaryUrl; assert it returns a string URL.
    expect($attachment->temporaryUrl(10))->toBeString()->toContain($attachment->path);
});

/*
 * Optional server-side mime verification (#audit S4 / Medium 1).
 */
it('rejects a spoofed mime when verify_real_mime is enabled', function () {
    config()->set('messenger.attachments.verify_real_mime', true);
    Storage::fake('local');

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // Claims image/jpeg + .jpg, but the real content sniffs as text/plain.
    $path = tempnam(sys_get_temp_dir(), 'spoof');
    file_put_contents($path, "this is plain text, not an image\n");
    $spoofed = new UploadedFile($path, 'evil.jpg', 'image/jpeg', null, true);

    Messenger::send($alice, $bob, ['attachments' => [$spoofed]]);
})->throws(InvalidAttachmentException::class);

it('allows a genuine file when verify_real_mime is enabled', function () {
    config()->set('messenger.attachments.verify_real_mime', true);
    Storage::fake('local');

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $message = Messenger::send($alice, $bob, [
        'attachments' => [UploadedFile::fake()->image('real.jpg', 8, 8)],
    ]);

    expect($message->attachments)->toHaveCount(1);
});

/*
 * Read-limit ceiling (#audit P1 / Medium 3).
 */
it('caps messages reads at the configured max_read_limit', function () {
    config()->set('messenger.messages.max_read_limit', 3);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    for ($i = 0; $i < 6; $i++) {
        Messenger::send($alice, $bob, "m{$i}");
    }

    $conversation = Messenger::between($alice, $bob);

    // No explicit limit → bounded to the ceiling instead of the full history.
    expect(Messenger::messages($conversation, $bob))->toHaveCount(3)
        // An over-cap explicit limit is also clamped down.
        ->and(Messenger::messages($conversation, $bob, ['limit' => 100]))->toHaveCount(3);
});

it('leaves reads unbounded when no max_read_limit is set (default)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        Messenger::send($alice, $bob, "m{$i}");
    }

    $conversation = Messenger::between($alice, $bob);

    expect(Messenger::messages($conversation, $bob))->toHaveCount(5);
});

it('normalizes limits with a ceiling correctly', function () {
    expect(Limit::normalize(null, 50))->toBe(50)
        ->and(Limit::normalize(10, 50))->toBe(10)
        ->and(Limit::normalize(100, 50))->toBe(50)
        ->and(Limit::normalize(-5, 50))->toBe(1)
        ->and(Limit::normalize(null, null))->toBeNull()
        ->and(Limit::normalize(0))->toBe(1);
});

/*
 * Install command (#audit High 4).
 */
it('publishes migrations via the install command', function () {
    $this->artisan('messenger:install')
        ->assertSuccessful();
});

it('reports whether the messenger tables exist', function () {
    // The test harness has already created the tables.
    expect(InstallCommand::tablesExist())->toBeTrue();
});

/*
 * Reports honour mass-assignment via the action (regression guard for #audit S1).
 */
it('creates reports through the action despite fillable restrictions', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $message = Messenger::send($alice, $bob, 'hi');

    $report = Messenger::report($message, $bob, 'spam', 'note');

    expect($report)->toBeInstanceOf(MessageReport::class)
        ->and($report->reason)->toBe('spam');
});
