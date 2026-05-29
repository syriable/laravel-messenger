<?php

namespace Syriable\Messenger\Listeners;

use Syriable\Messenger\Events\Broadcast\MessageSentBroadcast;
use Syriable\Messenger\Events\MessageSent;

/**
 * Bridges the domain {@see MessageSent} event onto the realtime layer. Only
 * registered when `messenger.broadcasting.enabled` is true, so the package
 * functions fully without realtime.
 */
class BroadcastMessageSent
{
    public function handle(MessageSent $event): void
    {
        broadcast(new MessageSentBroadcast($event->message));
    }
}
