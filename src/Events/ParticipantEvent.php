<?php

namespace Syriable\Messenger\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Syriable\Messenger\Models\Participant;

/**
 * Base for participant-specific conversation state events. Carries the
 * participant row whose state changed; the conversation is reachable via
 * $participant->conversation.
 */
abstract class ParticipantEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Participant $participant,
    ) {}
}
