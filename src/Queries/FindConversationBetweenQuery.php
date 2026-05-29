<?php

namespace Syriable\Messenger\Queries;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Support\ConversationKey;
use Syriable\Messenger\Support\Models;

/**
 * Resolves the single conversation between two participants, if one exists.
 * Read-only: never creates or mutates state.
 */
class FindConversationBetweenQuery
{
    public function execute(
        MessengerParticipant $a,
        MessengerParticipant $b,
        bool $withParticipants = true,
    ): ?Conversation {
        return Models::conversation()::query()
            ->when($withParticipants, fn ($query) => $query->with('participants'))
            ->where('key', ConversationKey::for($a, $b))
            ->first();
    }
}
