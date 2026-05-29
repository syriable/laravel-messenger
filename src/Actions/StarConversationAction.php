<?php

namespace Syriable\Messenger\Actions;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Events\ConversationStarred;
use Syriable\Messenger\Events\ConversationUnstarred;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Support\ResolvesParticipant;

/**
 * Stars (or unstars) a conversation for a single participant.
 */
class StarConversationAction
{
    use ResolvesParticipant;

    public function execute(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        $row = $this->resolveParticipant($conversation, $participant);

        $row->forceFill(['starred_at' => now()])->save();

        ConversationStarred::dispatch($row);

        return $row;
    }

    public function undo(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        $row = $this->resolveParticipant($conversation, $participant);

        $row->forceFill(['starred_at' => null])->save();

        ConversationUnstarred::dispatch($row);

        return $row;
    }
}
