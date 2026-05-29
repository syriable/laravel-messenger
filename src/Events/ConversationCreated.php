<?php

namespace Syriable\Messenger\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Syriable\Messenger\Models\Conversation;

class ConversationCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
    ) {}
}
