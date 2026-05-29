<?php

use Illuminate\Http\UploadedFile;
use Syriable\Messenger\Exceptions\InvalidAttachmentException;
use Syriable\Messenger\Exceptions\InvalidMessageException;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Exceptions\InvalidReplyException;
use Syriable\Messenger\Exceptions\InvalidReportException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Tests\Models\User;

// Issue #19 — message body length is bounded.
it('rejects a message body longer than the configured maximum', function () {
    config()->set('messenger.messages.max_body_length', 10);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, str_repeat('x', 11));
})->throws(InvalidMessageException::class);

it('accepts a body at the configured maximum length', function () {
    config()->set('messenger.messages.max_body_length', 10);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    expect(Messenger::send($alice, $bob, str_repeat('x', 10))->body)->toHaveLength(10);
});

// Issue #22 — indeterminate attachment size is rejected.
it('rejects an attachment whose size cannot be determined', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $file = new class('x.jpg') extends UploadedFile
    {
        public function __construct(string $name)
        {
            parent::__construct(tempnam(sys_get_temp_dir(), 'msg'), $name, 'image/jpeg', null, true);
        }

        public function getSize(): int|false
        {
            return false;
        }
    };

    Messenger::send($alice, $bob, ['attachments' => [$file]]);
})->throws(InvalidAttachmentException::class);

// Issue #20 — report reason/note are length-bounded.
it('rejects a report reason longer than the configured maximum', function () {
    config()->set('messenger.reports.max_reason_length', 8);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $message = Messenger::send($alice, $bob, 'hi');

    Messenger::report($message, $bob, reason: str_repeat('x', 9));
})->throws(InvalidReportException::class);

// Issue #25 — replies to cleared messages are rejected.
it('rejects a reply to a message the sender has cleared from view', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $old = Messenger::send($alice, $bob, 'old');
    $conversation = Messenger::between($alice, $bob);

    Messenger::clear($conversation, $bob);

    // A new message arrives so bob has something visible again.
    Messenger::send($alice, $bob, 'new');

    // bob replies to the pre-clear message → rejected.
    Messenger::send($bob, $alice, ['body' => 'reply', 'reply_to' => $old->getKey()]);
})->throws(InvalidReplyException::class);

// Issue #29 — a missing participant row fails the send.
it('fails the send when a participant row is missing', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'first');
    $conversation = Messenger::between($alice, $bob);

    // Corrupt the conversation by removing the recipient participant row.
    Participant::query()->forParticipant($bob)->delete();

    Messenger::send($alice, $bob, 'second');
})->throws(InvalidParticipantException::class);

// Issue #17 — archived conversations are excluded from unread totals.
it('excludes archived conversations from unread totals by default', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $carol = User::factory()->create();

    Messenger::send($alice, $bob, 'one');
    Messenger::send($carol, $bob, 'two');

    expect(Messenger::unreadCount($bob))->toBe(2)
        ->and(Messenger::unreadConversations($bob))->toBe(2);

    $conversation = Messenger::between($alice, $bob);
    Messenger::archive($conversation, $bob);

    expect(Messenger::unreadCount($bob))->toBe(1)
        ->and(Messenger::unreadConversations($bob))->toBe(1)
        ->and(Messenger::unreadCount($bob, includeArchived: true))->toBe(2);
});

// Issue #30 — trait methods match their names.
it('distinguishes unread message count from unread conversation count', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'one');
    Messenger::send($alice, $bob, 'two');

    expect($bob->unreadMessagesCount())->toBe(2)
        ->and($bob->unreadConversationsCount())->toBe(1);
});
