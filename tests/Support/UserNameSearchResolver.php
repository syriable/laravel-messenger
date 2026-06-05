<?php

namespace Syriable\Messenger\Tests\Support;

use Illuminate\Support\Collection;
use Syriable\Messenger\Contracts\ParticipantSearchResolver;
use Syriable\Messenger\Tests\Models\User;

/**
 * Test resolver that searches the test User model by name, demonstrating how a
 * host binds participant name search.
 */
class UserNameSearchResolver implements ParticipantSearchResolver
{
    public function search(string $term): Collection
    {
        return User::query()
            ->whereRaw('lower(name) like ?', ['%'.mb_strtolower($term).'%'])
            ->get();
    }
}
