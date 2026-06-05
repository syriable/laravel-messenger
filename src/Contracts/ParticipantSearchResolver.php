<?php

namespace Syriable\Messenger\Contracts;

use Illuminate\Support\Collection;

/**
 * Resolves which participants match a free-text search term, so inbox search can
 * find conversations by the other person's name/handle.
 *
 * The messaging domain stores only a participant's morph type and key — it has
 * no idea how the host models names — so name search is delegated here. The
 * default resolver returns nothing (name search disabled); bind your own to
 * search your users/sellers/agents, e.g.:
 *
 *     class UserSearch implements ParticipantSearchResolver {
 *         public function search(string $term): Collection {
 *             return User::where('name', 'like', "%{$term}%")->limit(50)->get();
 *         }
 *     }
 *
 * Message-body search works without a resolver. Implementations must never throw
 * and should bound their own result set.
 *
 * @method Collection<int, MessengerParticipant> search(string $term)
 */
interface ParticipantSearchResolver
{
    /**
     * @return Collection<int, MessengerParticipant>
     */
    public function search(string $term): Collection;
}
