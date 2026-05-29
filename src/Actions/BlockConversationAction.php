<?php

namespace Syriable\Messenger\Actions;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Events\ConversationBlocked;
use Syriable\Messenger\Events\ConversationUnblocked;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Support\ResolvesParticipant;

/**
 * Blocks (or unblocks) a conversation. Blocking is mutual: while a block is in
 * place neither participant may send messages, but the conversation history
 * remains visible and stored.
 */
class BlockConversationAction
{
    use ResolvesParticipant;

    public function execute(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        $row = $this->resolveParticipant($conversation, $participant);

        $row->forceFill(['blocked_at' => now()])->save();

        ConversationBlocked::dispatch($row);

        return $row;
    }

    public function undo(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        $row = $this->resolveParticipant($conversation, $participant);

        $row->forceFill(['blocked_at' => null])->save();

        ConversationUnblocked::dispatch($row);

        return $row;
    }
}
