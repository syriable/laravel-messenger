<?php

namespace Syriable\Messenger\Actions;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Events\ConversationMarkedAsSpam;
use Syriable\Messenger\Events\ConversationUnmarkedAsSpam;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Support\ResolvesParticipant;

/**
 * Marks (or unmarks) a conversation as spam. In v1 spam behaves like a block:
 * while it is set neither participant may send, but history is preserved.
 */
class SpamConversationAction
{
    use ResolvesParticipant;

    public function execute(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        $row = $this->resolveParticipant($conversation, $participant);

        $row->forceFill(['spammed_at' => now()])->save();

        ConversationMarkedAsSpam::dispatch($row);

        return $row;
    }

    public function undo(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        $row = $this->resolveParticipant($conversation, $participant);

        $row->forceFill(['spammed_at' => null])->save();

        ConversationUnmarkedAsSpam::dispatch($row);

        return $row;
    }
}
