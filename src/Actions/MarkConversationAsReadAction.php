<?php

namespace Syriable\Messenger\Actions;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Events\ConversationRead;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Support\ResolvesParticipant;

/**
 * Marks a conversation as fully read for a participant. This is the implicit
 * behaviour when a participant opens a conversation: all visible messages
 * become read. Reading never reorders the inbox.
 */
class MarkConversationAsReadAction
{
    use ResolvesParticipant;

    public function execute(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        $row = $this->resolveParticipant($conversation, $participant);

        $row = $this->applyLockedReset($row, [
            'last_read_at' => now(),
            'unread_count' => 0,
        ]);

        ConversationRead::dispatch($row);

        return $row;
    }
}
