<?php

namespace Syriable\Messenger\Actions;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Events\ConversationCleared;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Support\ResolvesParticipant;

/**
 * Clears a participant's visible history. No messages are deleted: a visibility
 * reset timestamp is recorded so only messages created after it remain visible
 * to this participant. The other participant is unaffected, and a new incoming
 * message makes the conversation visible again.
 */
class ClearConversationAction
{
    use ResolvesParticipant;

    public function execute(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        $row = $this->resolveParticipant($conversation, $participant);

        $row->forceFill([
            'cleared_at' => now(),
            'unread_count' => 0,
            'last_read_at' => now(),
        ])->save();

        ConversationCleared::dispatch($row);

        return $row;
    }
}
