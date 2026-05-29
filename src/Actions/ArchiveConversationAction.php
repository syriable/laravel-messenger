<?php

namespace Syriable\Messenger\Actions;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Events\ConversationArchived;
use Syriable\Messenger\Events\ConversationUnarchived;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Support\ResolvesParticipant;

/**
 * Archives (or unarchives) a conversation for a single participant. Archiving
 * is participant-specific and does not affect the other side.
 */
class ArchiveConversationAction
{
    use ResolvesParticipant;

    public function execute(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        $row = $this->resolveParticipant($conversation, $participant);

        $row->forceFill(['archived_at' => now()])->save();

        ConversationArchived::dispatch($row);

        return $row;
    }

    public function undo(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        $row = $this->resolveParticipant($conversation, $participant);

        $row->forceFill(['archived_at' => null])->save();

        ConversationUnarchived::dispatch($row);

        return $row;
    }
}
