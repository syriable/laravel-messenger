<?php

namespace Syriable\Messenger\Support;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Participant;

/**
 * Shared resolution of the participant row for a (conversation, participant)
 * pair. Used by the state-management actions to enforce conversation
 * membership before mutating participant-specific state.
 */
trait ResolvesParticipant
{
    protected function resolveParticipant(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        $row = $conversation->participants()
            ->forParticipant($participant)
            ->first();

        if ($row === null) {
            throw InvalidParticipantException::notInConversation();
        }

        return $row;
    }
}
