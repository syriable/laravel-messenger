<?php

namespace Syriable\Messenger\Support;

use Illuminate\Support\Facades\DB;
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

    /**
     * Apply an unread-resetting state change (e.g. mark-as-read, clear) safely
     * against a concurrent inbound increment. The participant row is locked for
     * the duration so an increment that lands during the reset is serialised
     * rather than silently overwritten.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function applyLockedReset(Participant $row, array $attributes): Participant
    {
        return DB::transaction(function () use ($row, $attributes) {
            $locked = $row->newQuery()->lockForUpdate()->find($row->getKey()) ?? $row;

            $locked->forceFill($attributes)->save();

            return $locked;
        });
    }
}
