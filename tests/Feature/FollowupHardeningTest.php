<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Data\StoredAttachment;
use Syriable\Messenger\Events\ConversationRead;
use Syriable\Messenger\Events\MessageReported;
use Syriable\Messenger\Exceptions\InvalidAttachmentException;
use Syriable\Messenger\Exceptions\InvalidReplyException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Queries\FindConversationBetweenQuery;
use Syriable\Messenger\Services\AttachmentService;
use Syriable\Messenger\Tests\Models\User;

// Issue #35 — participant-state events also respect the host's outer transaction.
it('defers ConversationRead until the enclosing transaction commits', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'hi');
    $conversation = Messenger::between($alice, $bob);

    Event::fake([ConversationRead::class]);

    DB::transaction(function () use ($conversation, $bob) {
        Messenger::markAsRead($conversation, $bob);
        Event::assertNotDispatched(ConversationRead::class);
    });

    Event::assertDispatched(ConversationRead::class);
});

it('does not dispatch ConversationRead when the enclosing transaction rolls back', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'hi');
    $conversation = Messenger::between($alice, $bob);

    Event::fake([ConversationRead::class]);

    try {
        DB::transaction(function () use ($conversation, $bob) {
            Messenger::markAsRead($conversation, $bob);

            throw new RuntimeException('roll back');
        });
    } catch (RuntimeException) {
        // expected
    }

    Event::assertNotDispatched(ConversationRead::class);
});

it('defers MessageReported until the enclosing transaction commits', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $message = Messenger::send($alice, $bob, 'hi');

    Event::fake([MessageReported::class]);

    DB::transaction(function () use ($message, $bob) {
        Messenger::report($message, $bob, reason: 'spam');
        Event::assertNotDispatched(MessageReported::class);
    });

    Event::assertDispatched(MessageReported::class);
});

// Issue #40 — failed disk write rejects the send and leaves nothing behind.
it('rejects the send and persists nothing when storeAs fails', function () {
    Storage::fake('local');

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // Stub the attachment service so storeAs() effectively fails.
    app()->bind(AttachmentService::class, fn () => new class extends AttachmentService
    {
        public function store(UploadedFile $file): StoredAttachment
        {
            throw InvalidAttachmentException::storageFailed($file->getClientOriginalName());
        }
    });

    expect(fn () => Messenger::send($alice, $bob, [
        'attachments' => [UploadedFile::fake()->image('x.jpg')],
    ]))->toThrow(InvalidAttachmentException::class);

    expect(Conversation::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

// Issue #37 — reply visibility is re-checked inside the write transaction.
it('rejects a reply when the sender clears history between pipeline and persist', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $old = Messenger::send($alice, $bob, 'old');
    $conversation = Messenger::between($alice, $bob);
    Messenger::send($alice, $bob, 'newer');

    // Resolve a conversation snapshot whose cleared_at is still null (as the
    // pipeline would have seen), but clear bob's history for real first so the
    // transaction-time re-check (which reads cleared_at fresh) catches it.
    Messenger::clear($conversation, $bob);

    app()->instance(FindConversationBetweenQuery::class, new class($conversation) extends FindConversationBetweenQuery
    {
        public function __construct(public Conversation $conversation) {}

        public function execute(MessengerParticipant $a, MessengerParticipant $b, bool $withParticipants = true): ?Conversation
        {
            // Return a copy whose loaded participant rows still show cleared_at = null.
            $fresh = $this->conversation->fresh(['participants']);
            $fresh?->participants->each(fn ($p) => $p->setAttribute('cleared_at', null));

            return $fresh;
        }
    });

    Messenger::send($bob, $alice, ['body' => 'reply', 'reply_to' => $old->getKey()]);
})->throws(InvalidReplyException::class);
