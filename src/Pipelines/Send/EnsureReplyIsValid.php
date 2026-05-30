<?php

namespace Syriable\Messenger\Pipelines\Send;

use Closure;
use Syriable\Messenger\Contracts\SendPipe;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Exceptions\InvalidReplyException;
use Syriable\Messenger\Support\Models;

/**
 * When a message replies to another, ensures the referenced message exists,
 * belongs to the same conversation, and is still visible to the sender (i.e.
 * was created after the sender's clear timestamp). A reply on a brand-new
 * conversation (which has no prior messages) is therefore always rejected, as
 * is a reply to a message the sender has cleared from their own history.
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

        if ($conversation === null) {
            throw InvalidReplyException::notInConversation();
        }

        $query = Models::message()::query()
            ->whereKey($replyToId)
            ->where('conversation_id', $conversation->getKey());

        // The replied-to message must still be visible to the sender: a message
        // created at or before the sender's clear timestamp has been removed
        // from their view and cannot be replied to.
        $clearedAt = $conversation->participantFor($message->sender)?->cleared_at;

        if ($clearedAt !== null) {
            $query->where('created_at', '>', $clearedAt->format('Y-m-d H:i:s.u'));
        }

        if (! $query->exists()) {
            throw InvalidReplyException::notInConversation();
        }

        return $next($message);
    }
}
