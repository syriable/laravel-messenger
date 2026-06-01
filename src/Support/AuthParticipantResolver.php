<?php

namespace Syriable\Messenger\Support;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Syriable\Messenger\Contracts\CurrentParticipantResolver;
use Syriable\Messenger\Contracts\MessengerParticipant;

/**
 * Default {@see CurrentParticipantResolver}: the authenticated user, when it is
 * itself a messenger participant. Returns null for guests or when the auth user
 * does not implement {@see MessengerParticipant}, so the UI degrades to an empty
 * state rather than erroring.
 */
class AuthParticipantResolver implements CurrentParticipantResolver
{
    public function __construct(protected AuthFactory $auth) {}

    public function resolve(): ?MessengerParticipant
    {
        $user = $this->auth->guard(config('messenger.ui.guard'))->user();

        return $user instanceof MessengerParticipant ? $user : null;
    }
}
