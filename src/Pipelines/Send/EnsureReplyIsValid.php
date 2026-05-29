<?php

namespace Syriable\Messenger\Pipelines\Send;

use Closure;
use Syriable\Messenger\Contracts\SendPipe;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Exceptions\InvalidReplyException;
use Syriable\Messenger\Support\Models;

/**
 * When a message replies to another, ensures the referenced message exists and
 * belongs to the same conversation. A reply on a brand-new conversation (which
 * has no prior messages) is therefore always rejected.
 */
class EnsureReplyIsValid implements SendPipe
{
    public function handle(PendingMessage $message, Closure $next): PendingMessage
    {
        $replyToId = $message->message->replyToId;

        if ($replyToId === null) {
            return $next($message);
        }

        $conversation = $message->conversation;

        $belongsToConversation = $conversation !== null && Models::message()::query()
            ->whereKey($replyToId)
            ->where('conversation_id', $conversation->getKey())
            ->exists();

        if (! $belongsToConversation) {
            throw InvalidReplyException::notInConversation();
        }

        return $next($message);
    }
}
