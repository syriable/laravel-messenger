<?php

namespace Syriable\Messenger\Tests\Support;

use Carbon\CarbonInterface;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Contracts\PresenceResolver;

/**
 * Test double that reports every participant as online. Lives in the autoloaded
 * test-support namespace (not inside a test file) so it can be referenced by
 * class-string from any test regardless of file-load order.
 */
class FakeOnlinePresenceResolver implements PresenceResolver
{
    public function status(MessengerParticipant $participant): string
    {
        return 'online';
    }

    public function lastSeenAt(MessengerParticipant $participant): ?CarbonInterface
    {
        return now();
    }
}
