<?php

namespace Syriable\Messenger\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Support\Limit;

/**
 * Retrieves the messages of a conversation as visible to a given participant,
 * ordered chronologically (newest at the bottom of the timeline).
 *
 * Messages created at or before the participant's clear timestamp are hidden.
 * Relations are eager-loaded to avoid N+1 queries.
 *
 * Supported options:
 *  - limit (?int)      — return at most N visible messages.
 *  - before_id (string)— keyset cursor: only messages older than this message id
 *                        (exclusive). Combined with limit, loads "the previous N
 *                        messages" for scroll-up pagination.
 *  - after_id (string) — keyset cursor: only messages newer than this message id
 *                        (exclusive). Combined with limit, loads "the next N
 *                        messages" since the cursor.
 *
 * The result is always returned in chronological order regardless of cursor
 * direction. before_id and after_id are mutually exclusive.
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

        $limit = Limit::normalize($options['limit'] ?? null);

        $beforeId = $options['before_id'] ?? null;
        $afterId = $options['after_id'] ?? null;

        if ($beforeId !== null && $afterId !== null) {
            throw new \InvalidArgumentException(
                'The before_id and after_id message cursors are mutually exclusive.'
            );
        }

        $query = $conversation->messages()
            ->with(['attachments', 'replyTo'])
            ->when(
                $row->cleared_at !== null,
                // Format with microseconds so the boundary is not truncated to
                // the second when bound into the query (#63).
                fn ($query) => $query->where('created_at', '>', $row->cleared_at->format('Y-m-d H:i:s.u')),
            );

        if ($beforeId !== null) {
            $anchor = $this->resolveCursor($conversation, $beforeId);
            $query->where($this->keyset($anchor, '<'));

            // Take the N immediately before the cursor, then restore order.
            return $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->when($limit !== null, fn ($q) => $q->limit($limit))
                ->get()
                ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                ->values();
        }

        if ($afterId !== null) {
            $anchor = $this->resolveCursor($conversation, $afterId);
            $query->where($this->keyset($anchor, '>'));

            return $query
                ->orderBy('created_at')
                ->orderBy('id')
                ->when($limit !== null, fn ($q) => $q->limit($limit))
                ->get();
        }

        if ($limit !== null) {
            // Take the latest N, then restore chronological order.
            return $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->sortBy([['created_at', 'asc'], ['id', 'asc']])
                ->values();
        }

        return $query->chronological()->get();
    }

    /**
     * Resolve a cursor message id to a Message that belongs to the conversation.
     */
    protected function resolveCursor(Conversation $conversation, string $messageId): Message
    {
        /** @var ?Message $message */
        $message = $conversation->messages()->whereKey($messageId)->first();

        if ($message === null) {
            throw new \InvalidArgumentException(
                "Cursor message [{$messageId}] does not belong to this conversation."
            );
        }

        return $message;
    }

    /**
     * Build a strict keyset comparison against the (created_at, id) tuple so the
     * cursor is stable even when messages share a microsecond timestamp.
     *
     * @return \Closure(Builder<Message>): void
     */
    protected function keyset(Message $anchor, string $operator): \Closure
    {
        $createdAt = $anchor->created_at->format('Y-m-d H:i:s.u');
        $id = $anchor->getKey();

        return function (Builder $q) use ($operator, $createdAt, $id): void {
            $q->where('created_at', $operator, $createdAt)
                ->orWhere(function (Builder $tie) use ($operator, $createdAt, $id): void {
                    $tie->where('created_at', $createdAt)
                        ->where('id', $operator, $id);
                });
        };
    }
}
