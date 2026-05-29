<?php

namespace Syriable\Messenger\Contracts;

use Closure;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Exceptions\MessengerException;

/**
 * A single stage in the send-message pipeline.
 *
 * Pipes are composable, lightweight and configurable via the
 * `messenger.pipeline` config key. They are used for validation, filtering,
 * moderation and pre-send checks. A pipe either passes control to the next
 * stage via $next or throws a {@see MessengerException}.
 */
interface SendPipe
{
    public function handle(PendingMessage $message, Closure $next): PendingMessage;
}
