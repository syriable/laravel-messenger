<?php

namespace Syriable\Messenger\Queries;

use Illuminate\Support\Collection;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Support\Limit;
use Syriable\Messenger\Support\Models;

/**
 * Retrieves a participant's saved (bookmarked) messages, most recently saved
 * first. Joins the saved-messages table and eager-loads relations to avoid N+1.
 *
 * Supported options: conversation_id (string) to scope to one conversation (the
 * per-conversation "Saved" tab), and limit (?int).
 *
 * @return Collection<int, Message>
 */
class GetSavedMessagesQuery
{
    public function execute(MessengerParticipant $participant, array $options = []): Collection
    {
        $messages = Models::newMessage()->getTable();
        $saved = Models::newSavedMessage()->getTable();

        $limit = Limit::normalize($options['limit'] ?? null);
        $conversationId = $options['conversation_id'] ?? null;

        return Models::message()::query()
            ->join("{$saved} as ms", 'ms.message_id', '=', "{$messages}.id")
            ->where('ms.participant_type', $participant->getMorphClass())
            ->where('ms.participant_id', $participant->getKey())
            ->when($conversationId !== null, fn ($query) => $query->where("{$messages}.conversation_id", $conversationId))
            ->with(['attachments', 'replyTo'])
            ->orderByDesc('ms.created_at')
            ->orderByDesc('ms.id')
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->select("{$messages}.*")
            ->get();
    }

    public function isSaved(Message $message, MessengerParticipant $participant): bool
    {
        return Models::savedMessage()::query()
            ->forParticipant($participant)
            ->where('message_id', $message->getKey())
            ->exists();
    }
}
