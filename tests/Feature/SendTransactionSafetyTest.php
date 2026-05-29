<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Syriable\Messenger\Events\MessageSent;
use Syriable\Messenger\Exceptions\ConversationBlockedException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Tests\Models\User;

// Issue #23 — send events fire only after the host's outer transaction commits.
it('does not dispatch MessageSent until an enclosing transaction commits', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Event::fake([MessageSent::class]);

    DB::transaction(function () use ($alice, $bob) {
        Messenger::send($alice, $bob, 'hi');

        // Still inside the outer transaction: the event must not have fired yet.
        Event::assertNotDispatched(MessageSent::class);
    });

    // After the outer transaction commits, the event is dispatched.
    Event::assertDispatched(MessageSent::class);
});

it('does not dispatch MessageSent when the enclosing transaction rolls back', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Event::fake([MessageSent::class]);

    try {
        DB::transaction(function () use ($alice, $bob) {
            Messenger::send($alice, $bob, 'hi');

            throw new RuntimeException('roll back');
        });
    } catch (RuntimeException) {
        // expected
    }

    Event::assertNotDispatched(MessageSent::class);
    expect(Conversation::query()->count())->toBe(0);
});

// Issue #28 — block/spam is re-checked inside the write transaction.
it('rejects a send when the conversation was blocked after the pipeline ran', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'first');
    $conversation = Messenger::between($alice, $bob);

    // Block via a separate, freshly-loaded conversation instance so the
    // in-memory copy used by the next send still looks un-blocked — the
    // transaction-time re-check must catch it.
    Messenger::block(Conversation::query()->with('participants')->findOrFail($conversation->getKey()), $alice);

    Messenger::send($bob, $alice, 'after block');
})->throws(ConversationBlockedException::class);
