<?php

namespace Syriable\Messenger\Queries;

use Illuminate\Database\Eloquent\Builder;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Support\Models;

/**
 * Returns unread totals for a participant using the denormalised unread_count
 * column, so no message scanning is required.
 *
 * Archived conversations are excluded by default, mirroring the default inbox
 * visibility; pass include_archived: true to count them too.
 */
class GetUnreadCountQuery
{
    /**
     * Total number of unread messages across the participant's conversations.
     */
    public function execute(MessengerParticipant $participant, bool $includeArchived = false): int
    {
        return (int) $this->base($participant, $includeArchived)->sum('unread_count');
    }

    /**
     * Number of conversations that contain at least one unread message.
     */
    public function conversations(MessengerParticipant $participant, bool $includeArchived = false): int
    {
        return $this->base($participant, $includeArchived)->where('unread_count', '>', 0)->count();
    }

    /**
     * @return Builder<Participant>
     */
    private function base(MessengerParticipant $participant, bool $includeArchived): Builder
    {
        return Models::participant()::query()
            ->forParticipant($participant)
            ->when(! $includeArchived, fn (Builder $query) => $query->whereNull('archived_at'));
    }
}
