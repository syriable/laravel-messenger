<?php

namespace Syriable\Messenger\Support;

use Carbon\CarbonInterface;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Contracts\PresenceResolver;

/**
 * Default {@see PresenceResolver}: reports everyone offline with no last-seen.
 *
 * Real presence requires a transport (presence channels, a heartbeat cache, or
 * a host `last_seen_at` column), which the headless package does not assume.
 * Bind your own resolver via `messenger.ui.presence_resolver` to light up the
 * online dots and "last seen" line.
 */
class NullPresenceResolver implements PresenceResolver
{
    public function status(MessengerParticipant $participant): string
    {
        return 'offline';
    }

    public function lastSeenAt(MessengerParticipant $participant): ?CarbonInterface
    {
        return null;
    }
}
