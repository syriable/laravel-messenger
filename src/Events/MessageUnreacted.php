<?php

namespace Syriable\Messenger\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Models\Message;

/**
 * A participant removed an emoji reaction from a message. The reaction row is
 * gone, so the message, participant and emoji are carried directly.
 */
class MessageUnreacted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Message $message,
        public readonly MessengerParticipant $participant,
        public readonly string $emoji,
    ) {}
}
