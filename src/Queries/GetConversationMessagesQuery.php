<?php

namespace Syriable\Messenger\Queries;

use Illuminate\Support\Collection;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;

/**
 * Retrieves the messages of a conversation as visible to a given participant,
 * ordered chronologically (newest at the bottom of the timeline).
 *
 * Messages created at or before the participant's clear timestamp are hidden.
 * Relations are eager-loaded to avoid N+1 queries.
 *
 * Supported options: limit (?int) — return the latest N visible messages, still
 * in chronological order.
 *
 * @return Collection<int, Message>
 */
class GetConversationMessagesQuery
{
    public function execute(
        Conversation $conversation,
        MessengerParticipant $participant,
        array $options = [],
    ): Collection {
        $row = $conversation->participants()
            ->forParticipant($participant)
            ->first();

        if ($row === null) {
            throw InvalidParticipantException::notInConversation();
        }

        $limit = $options['limit'] ?? null;

        $query = $conversation->messages()
            ->with(['attachments', 'replyTo'])
            ->when(
                $row->cleared_at !== null,
                fn ($query) => $query->where('created_at', '>', $row->cleared_at),
            );

        if ($limit !== null) {
            // Take the latest N, then restore chronological order.
            return $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit((int) $limit)
                ->get()
                ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                ->values();
        }

        return $query->chronological()->get();
    }
}
