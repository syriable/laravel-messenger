<?php

namespace Syriable\Messenger\Contracts;

use Carbon\CarbonInterface;

/**
 * Resolves a participant's presence for the UI ("online", "last seen …").
 *
 * Presence is a transport concern, not messaging state, so the domain stores
 * none of it. This is the swappable boundary: the default resolver reports
 * everyone offline (presence requires a realtime channel), and hosts bind their
 * own — backed by presence channels, a cached heartbeat, or a `last_seen_at`
 * column — via `messenger.ui.presence_resolver`. Implementations must never
 * throw and must tolerate a participant with no recorded presence.
 */
interface PresenceResolver
{
    /**
     * One of: "online", "away", "offline".
     */
    public function status(MessengerParticipant $participant): string;

    /**
     * When the participant was last seen, or null when unknown.
     */
    public function lastSeenAt(MessengerParticipant $participant): ?CarbonInterface;
}
