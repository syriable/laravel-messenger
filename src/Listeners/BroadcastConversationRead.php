<?php

namespace Syriable\Messenger\Listeners;

use Syriable\Messenger\Events\Broadcast\ConversationReadBroadcast;
use Syriable\Messenger\Events\ConversationRead;

/**
 * Bridges the domain {@see ConversationRead} event onto the realtime layer. Only
 * registered when `messenger.broadcasting.enabled` is true, so the package
 * functions fully without realtime.
 */
class BroadcastConversationRead
{
    public function handle(ConversationRead $event): void
    {
        broadcast(new ConversationReadBroadcast($event->participant));
    }
}
