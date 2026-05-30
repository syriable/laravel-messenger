<?php

namespace Syriable\Messenger\Actions;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Events\ConversationMarkedAsUnread;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Support\ResolvesParticipant;

/**
 * Manually marks a conversation as unread for a participant. Only the last
 * received message is treated as unread (the denormalised counter is set to 1),
 * and the inbox ordering is never affected (ordering depends solely on latest
 * message activity).
 *
 * If the participant has no received message that is still visible to them
 * (e.g. they only ever sent messages, or they have cleared their history), the
 * call is a no-op: a conversation can never show unread with nothing to read.
 */
class MarkConversationAsUnreadAction
{
    use ResolvesParticipant;

    public function execute(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        $row = $this->resolveParticipant($conversation, $participant);

        if (! $this->hasVisibleReceivedMessage($conversation, $participant, $row)) {
            return $row;
        }

        $row->forceFill([
            'unread_count' => 1,
            'last_read_at' => null,
        ])->save();

        ConversationMarkedAsUnread::dispatch($row);

        return $row;
    }

    private function hasVisibleReceivedMessage(
        Conversation $conversation,
        MessengerParticipant $participant,
        Participant $row,
    ): bool {
        return $conversation->messages()
            ->where(function ($query) use ($participant) {
                $query->where('sender_type', '!=', $participant->getMorphClass())
                    ->orWhere('sender_id', '!=', $participant->getKey());
            })
            ->when(
                $row->cleared_at !== null,
                fn ($query) => $query->where('created_at', '>', $row->cleared_at->format('Y-m-d H:i:s.u')),
            )
            ->exists();
    }
}
