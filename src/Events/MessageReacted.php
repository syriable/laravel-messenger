<?php

namespace Syriable\Messenger\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Syriable\Messenger\Models\MessageReaction;

/**
 * A participant added an emoji reaction to a message.
 */
class MessageReacted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly MessageReaction $reaction,
    ) {}
}
