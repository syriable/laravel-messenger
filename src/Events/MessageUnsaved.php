<?php

namespace Syriable\Messenger\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Models\Message;

/**
 * A participant removed a message from their saved set. The saved row no longer
 * exists, so the message and the participant are carried directly.
 */
class MessageUnsaved implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Message $message,
        public readonly MessengerParticipant $participant,
    ) {}
}
