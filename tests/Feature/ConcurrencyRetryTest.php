<?php

use Syriable\Messenger\Actions\SendMessageAction;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Tests\Models\User;

/**
 * Fails the very first write transaction with a concurrency-style error (the
 * string Laravel recognises as a transient lock/deadlock), then behaves
 * normally — letting us assert the bounded transaction retry recovers the send.
 */
class FlakyOnceSendAction extends SendMessageAction
{
    public int $projectionCalls = 0;

    protected function updateProjections(Conversation $conversation, Message $message, PendingMessage $pending): void
    {
        $this->projectionCalls++;

        if ($this->projectionCalls === 1) {
            // Recognised by Laravel's DetectsConcurrencyErrors → triggers a
            // rollback + retry rather than propagating.
            throw new RuntimeException('database is locked');
        }

        parent::updateProjections($conversation, $message, $pending);
    }
}

it('retries the write transaction on a transient concurrency error without duplicating the message', function () {
    app()->bind(SendMessageAction::class, FlakyOnceSendAction::class);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // The first transaction attempt throws "database is locked" and rolls back;
    // the bounded retry then succeeds.
    $message = Messenger::send($alice, $bob, 'survives the lock');

    $conversation = Messenger::between($alice, $bob);

    expect($message->body)->toBe('survives the lock')
        // Exactly one message — the rolled-back first attempt left nothing behind.
        ->and(Messenger::messages($conversation, $alice))->toHaveCount(1)
        // The recipient's unread counter incremented exactly once, not twice.
        ->and($conversation->participantFor($bob)->unread_count)->toBe(1);
});

it('gives up after the bounded number of attempts when the error never clears', function () {
    app()->bind(SendMessageAction::class, AlwaysLockedSendAction::class);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'never lands');
})->throws(RuntimeException::class, 'database is locked');

class AlwaysLockedSendAction extends SendMessageAction
{
    protected function updateProjections(Conversation $conversation, Message $message, PendingMessage $pending): void
    {
        throw new RuntimeException('database is locked');
    }
}
