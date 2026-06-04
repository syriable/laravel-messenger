<?php

namespace Syriable\Messenger\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Syriable\Messenger\Models\SavedMessage;

/**
 * A participant saved (bookmarked) a message.
 */
class MessageSaved implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SavedMessage $savedMessage,
    ) {}
}
