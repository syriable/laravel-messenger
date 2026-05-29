<?php

namespace Syriable\Messenger\Pipelines\Send;

use Closure;
use Syriable\Messenger\Contracts\SendPipe;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Exceptions\InvalidMessageException;

/**
 * A valid message must carry a body, at least one attachment, or both. An empty
 * message is rejected.
 */
class EnsureMessageHasContent implements SendPipe
{
    public function handle(PendingMessage $message, Closure $next): PendingMessage
    {
        if (! $message->message->hasBody() && ! $message->message->hasAttachments()) {
            throw InvalidMessageException::empty();
        }

        return $next($message);
    }
}
