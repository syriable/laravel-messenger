<?php

namespace Syriable\Messenger\Support;

use Illuminate\Support\Collection;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Contracts\ParticipantSearchResolver;

/**
 * Default {@see ParticipantSearchResolver}: matches no participants, so inbox
 * search falls back to message-body matching only. Bind a host resolver via
 * `messenger.ui.search_resolver` to also search by participant name/handle.
 */
class NullParticipantSearchResolver implements ParticipantSearchResolver
{
    /**
     * @return Collection<int, MessengerParticipant>
     */
    public function search(string $term): Collection
    {
        return collect();
    }
}
