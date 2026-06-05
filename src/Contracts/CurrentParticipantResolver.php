<?php

namespace Syriable\Messenger\Contracts;

/**
 * Resolves the participant whose inbox the UI is currently rendering.
 *
 * The messaging domain is identity-agnostic, but a UI needs to know "who am I"
 * to show the right inbox. This is the swappable boundary for that: the default
 * returns the authenticated user (when it is a {@see MessengerParticipant}), but
 * a host whose participant is not always the auth user — an admin acting as a
 * support agent, an impersonation layer, a tenant context — binds its own.
 */
interface CurrentParticipantResolver
{
    public function resolve(): ?MessengerParticipant;
}
