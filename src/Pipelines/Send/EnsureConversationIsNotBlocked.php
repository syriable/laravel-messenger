<?php

namespace Syriable\Messenger\Pipelines\Send;

use Closure;
use Syriable\Messenger\Contracts\SendPipe;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Exceptions\ConversationBlockedException;
use Syriable\Messenger\Models\Participant;

/**
 * Enforces the mutual block/spam rule: if either participant has blocked or
 * spammed the conversation, neither side may send a new message. History is
 * untouched — only sending is prevented.
 */
class EnsureConversationIsNotBlocked implements SendPipe
{
    public function handle(PendingMessage $message, Closure $next): PendingMessage
    {
        $conversation = $message->conversation;

        // A brand-new conversation (no row yet) cannot be blocked.
        if ($conversation === null) {
            return $next($message);
        }

        $blocked = $conversation->participants
            ->contains(fn (Participant $row) => $row->isBlocking() || $row->isSpamming());

        if ($blocked) {
            throw ConversationBlockedException::make();
        }

        return $next($message);
    }
}
