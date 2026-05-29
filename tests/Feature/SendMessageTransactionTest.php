<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Actions\SendMessageAction;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Events\ConversationCreated;
use Syriable\Messenger\Events\MessageSent;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Queries\FindConversationBetweenQuery;
use Syriable\Messenger\Services\AttachmentService;
use Syriable\Messenger\Support\ConversationKey;
use Syriable\Messenger\Tests\Models\User;

// Issue #3 — concurrent first message must not fail with a unique violation.
it('recovers from a lost first-message race instead of failing', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // Simulate the other request winning the create race: the conversation
    // already exists, but the first lookup reports "none" (as it would for the
    // request that read before the winner committed).
    $existing = Conversation::query()->create(['key' => ConversationKey::for($alice, $bob)]);
    $existing->participants()->createMany([
        ['participant_type' => $alice->getMorphClass(), 'participant_id' => $alice->getKey()],
        ['participant_type' => $bob->getMorphClass(), 'participant_id' => $bob->getKey()],
    ]);

    app()->instance(FindConversationBetweenQuery::class, new class($existing) extends FindConversationBetweenQuery
    {
        public int $calls = 0;

        public function __construct(public Conversation $existing) {}

        public function execute(MessengerParticipant $a, MessengerParticipant $b, bool $withParticipants = true): ?Conversation
        {
            // Null on the first lookup (the race), the real conversation on retry.
            return $this->calls++ === 0 ? null : $this->existing->load('participants');
        }
    });

    $message = Messenger::send($alice, $bob, 'hi after race');

    expect(Conversation::query()->count())->toBe(1)
        ->and($message->conversation_id)->toBe($existing->id)
        ->and($message->body)->toBe('hi after race');
});

// Issue #4 — unread is incremented atomically from the database value.
it('increments unread from the database value, not a stale in-memory snapshot', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'one');

    // A snapshot taken while bob's unread is 1.
    $snapshot = Conversation::query()->with('participants')->first();

    // Simulate a concurrent message landing: the DB counter jumps to 10, but the
    // snapshot above still believes it is 1.
    Participant::query()->forParticipant($bob)->update(['unread_count' => 10]);

    // Force the next send to use the stale snapshot.
    app()->instance(FindConversationBetweenQuery::class, new class($snapshot) extends FindConversationBetweenQuery
    {
        public function __construct(public Conversation $conversation) {}

        public function execute(MessengerParticipant $a, MessengerParticipant $b, bool $withParticipants = true): ?Conversation
        {
            return $this->conversation;
        }
    });

    Messenger::send($alice, $bob, 'two');

    // Atomic increment: 10 + 1 = 11. A stale read-modify-write would write 1 + 1 = 2.
    expect((int) Participant::query()->forParticipant($bob)->value('unread_count'))->toBe(11);
});

// Issue #7 — a failed send leaves no orphaned attachment files on disk.
it('cleans up stored attachment files when the send transaction fails', function () {
    Storage::fake('local');

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    app()->bind(SendMessageAction::class, fn ($app) => new class($app->make(AttachmentService::class), $app->make(FindConversationBetweenQuery::class)) extends SendMessageAction
    {
        protected function persistMessage(Conversation $conversation, PendingMessage $pending, array $stored): Message
        {
            throw new RuntimeException('boom');
        }
    });

    expect(fn () => Messenger::send($alice, $bob, [
        'attachments' => [UploadedFile::fake()->image('photo.jpg')],
    ]))->toThrow(RuntimeException::class);

    expect(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(Conversation::query()->count())->toBe(0);
});

// Issue #8 — domain events are dispatched only after the transaction commits.
it('dispatches MessageSent and ConversationCreated after the transaction commits', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $levels = [];
    Event::listen(ConversationCreated::class, function () use (&$levels) {
        $levels['created'] = DB::transactionLevel();
    });
    Event::listen(MessageSent::class, function () use (&$levels) {
        $levels['sent'] = DB::transactionLevel();
    });

    Messenger::send($alice, $bob, 'hi');

    // 0 means no open transaction when the listener ran (i.e. post-commit).
    expect($levels['created'])->toBe(0)
        ->and($levels['sent'])->toBe(0);
});
