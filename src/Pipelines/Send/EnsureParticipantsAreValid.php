<?php

namespace Syriable\Messenger\Pipelines\Send;

use Closure;
use Syriable\Messenger\Contracts\SendPipe;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Exceptions\InvalidParticipantException;

/**
 * Rejects a message whose sender and recipient are the same participant. There
 * can never be a conversation between a participant and itself.
 */
class EnsureParticipantsAreValid implements SendPipe
{
    public function handle(PendingMessage $message, Closure $next): PendingMessage
    {
        $sender = $message->sender;
        $recipient = $message->recipient;

        $sameParticipant = $sender->getMorphClass() === $recipient->getMorphClass()
            && (string) $sender->getKey() === (string) $recipient->getKey();

        if ($sameParticipant) {
            throw InvalidParticipantException::sameParticipant();
        }

        return $next($message);
    }
}
