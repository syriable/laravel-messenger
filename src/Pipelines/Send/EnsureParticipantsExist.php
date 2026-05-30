<?php

namespace Syriable\Messenger\Pipelines\Send;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Exceptions\InvalidParticipantException;

/**
 * Optionally verifies that both the sender and recipient actually exist in the
 * database before a conversation is created, preventing "ghost" participants
 * (an unsaved or already-deleted model with a manually-set key).
 *
 * Disabled by default; enable via `messenger.validation.verify_participants_exist`.
 * Participant existence is otherwise the host application's responsibility.
 */
class EnsureParticipantsExist implements SendPipe
{
    public function handle(PendingMessage $message, Closure $next): PendingMessage
    {
        if (! config('messenger.validation.verify_participants_exist', false)) {
            return $next($message);
        }

        foreach ([$message->sender, $message->recipient] as $participant) {
            if (! $this->exists($participant)) {
                throw InvalidParticipantException::doesNotExist();
            }
        }

        return $next($message);
    }

    private function exists(MessengerParticipant $participant): bool
    {
        $class = Relation::getMorphedModel($participant->getMorphClass()) ?? $participant->getMorphClass();

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            // Unknown/non-Eloquent participant: treat existence as the host's concern.
            return true;
        }

        return $class::query()->whereKey($participant->getKey())->exists();
    }
}
