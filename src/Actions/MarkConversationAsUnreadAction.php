<?php

namespace Syriable\Messenger\Actions;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Events\ConversationMarkedAsUnread;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Support\ResolvesParticipant;

/**
 * Manually marks a conversation as unread for a participant. Only the last
 * received message is treated as unread, and the inbox ordering is never
 * affected (ordering depends solely on latest message activity).
 */
class MarkConversationAsUnreadAction
{
    use ResolvesParticipant;

    public function execute(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        $row = $this->resolveParticipant($conversation, $participant);

        $row->forceFill([
            'unread_count' => 1,
            'last_read_at' => null,
        ])->save();

        ConversationMarkedAsUnread::dispatch($row);

        return $row;
    }
}
