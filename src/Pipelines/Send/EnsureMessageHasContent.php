<?php

namespace Syriable\Messenger\Pipelines\Send;

use Closure;
use Syriable\Messenger\Contracts\SendPipe;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Exceptions\InvalidMessageException;

/**
 * A valid message must carry a body, at least one attachment, or both. An empty
 * message is rejected, as is a body exceeding the configured maximum length.
 */
class EnsureMessageHasContent implements SendPipe
{
    public function handle(PendingMessage $message, Closure $next): PendingMessage
    {
        $body = $message->message->body;

        if (! $message->message->hasBody() && ! $message->message->hasAttachments()) {
            throw InvalidMessageException::empty();
        }

        $maxLength = config('messenger.messages.max_body_length');

        if ($body !== null && $maxLength !== null && mb_strlen($body) > (int) $maxLength) {
            throw InvalidMessageException::bodyTooLong((int) $maxLength);
        }

        return $next($message);
    }
}
