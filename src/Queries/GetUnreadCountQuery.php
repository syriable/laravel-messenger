<?php

namespace Syriable\Messenger\Queries;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Support\Models;

/**
 * Returns unread totals for a participant using the denormalised unread_count
 * column, so no message scanning is required.
 */
class GetUnreadCountQuery
{
    /**
     * Total number of unread messages across all of the participant's
     * conversations.
     */
    public function execute(MessengerParticipant $participant): int
    {
        return (int) $this->base($participant)->sum('unread_count');
    }

    /**
     * Number of conversations that contain at least one unread message.
     */
    public function conversations(MessengerParticipant $participant): int
    {
        return $this->base($participant)->where('unread_count', '>', 0)->count();
    }

    private function base(MessengerParticipant $participant)
    {
        return Models::participant()::query()
            ->forParticipant($participant);
    }
}
