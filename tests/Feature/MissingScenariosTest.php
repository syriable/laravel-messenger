<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Syriable\Messenger\Events\Broadcast\MessageSentBroadcast;
use Syriable\Messenger\Events\MessageSent;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Tests\Models\User;

/*
 * Missing-test coverage identified by the audit.
 */

// Host-wrapped transaction rollback: nothing persists and the after-commit
// domain event never fires when the host's enclosing transaction rolls back.
it('does not persist or dispatch when the host transaction rolls back', function () {
    Event::fake([MessageSent::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    try {
        DB::transaction(function () use ($alice, $bob) {
            Messenger::send($alice, $bob, 'in a doomed transaction');

            throw new RuntimeException('host rollback');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(Conversation::count())->toBe(0)
        ->and(Message::count())->toBe(0);

    Event::assertNotDispatched(MessageSent::class);
});

it('persists and dispatches once the host transaction commits', function () {
    Event::fake([MessageSent::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    DB::transaction(function () use ($alice, $bob) {
        Messenger::send($alice, $bob, 'committed');
    });

    expect(Message::count())->toBe(1);

    Event::assertDispatched(MessageSent::class);
});

// Custom model override: the send action resolves the message model through the
// config-driven resolver, so a host subclass is honoured end to end.
it('honours a custom message model configured via messenger.models', function () {
    config()->set('messenger.models.message', CustomMessengerMessage::class);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $message = Messenger::send($alice, $bob, 'hi');

    expect($message)->toBeInstanceOf(CustomMessengerMessage::class);
});

// Queued broadcast rehydration: broadcastWith() must not error when the message
// was serialized/rehydrated without its attachments relation loaded.
it('builds the broadcast payload after a relation-less rehydration', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $message = Messenger::send($alice, $bob, 'hello');

    // Simulate the queued case: a fresh instance with no loaded relations.
    $fresh = Message::query()->findOrFail($message->getKey());

    $payload = (new MessageSentBroadcast($fresh))->broadcastWith();

    expect($payload['id'])->toBe($message->getKey())
        ->and($payload['has_attachments'])->toBeFalse()
        ->and($payload['attachments'])->toBe([]);
});

class CustomMessengerMessage extends Message {}
