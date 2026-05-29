<?php

namespace Syriable\Messenger\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Syriable\Messenger\Models\Participant;

/**
 * Base for participant-specific conversation state events. Carries the
 * participant row whose state changed; the conversation is reachable via
 * $participant->conversation.
 *
 * Implements ShouldDispatchAfterCommit so that, when a host wraps a write in
 * its own database transaction, listeners and broadcasts only run after that
 * transaction commits — never against rows that may be rolled back.
 */
abstract class ParticipantEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Participant $participant,
    ) {}
}
