<?php

namespace Syriable\Messenger\Queries;

use Illuminate\Support\Collection;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Support\Limit;
use Syriable\Messenger\Support\Models;

/**
 * Retrieves a participant's inbox, ordered solely by latest message activity.
 *
 * Unread state never affects ordering. Cleared conversations only reappear once
 * a message arrives after the clear. The query joins the participant row and
 * eager-loads relations to avoid N+1 queries, relying on the
 * (participant_type, participant_id) and (last_message_at) indexes.
 *
 * Supported options: include_archived (bool), starred (bool), limit (?int),
 * with_participant_models (bool). The last eager-loads the polymorphic model
 * behind each Participant row (e.g. the User) so the host can render names and
 * avatars without an N+1 (#70).
 *
 * @return Collection<int, Conversation>
 */
class GetInboxConversationsQuery
{
    public function execute(MessengerParticipant $participant, array $options = []): Collection
    {
        $conversations = Models::newConversation()->getTable();
        $participants = Models::newParticipant()->getTable();

        $includeArchived = (bool) ($options['include_archived'] ?? false);
        $starredOnly = (bool) ($options['starred'] ?? false);
        $withParticipantModels = (bool) ($options['with_participant_models'] ?? false);
        $limit = Limit::normalize($options['limit'] ?? null);

        // Eager-load the morphTo target in a single grouped query pass when the
        // host needs participant names/avatars, instead of one lookup per row.
        $participantsRelation = $withParticipantModels
            ? 'participants.participant'
            : 'participants';

        return Models::conversation()::query()
            ->join("{$participants} as mp", 'mp.conversation_id', '=', "{$conversations}.id")
            ->where('mp.participant_type', $participant->getMorphClass())
            ->where('mp.participant_id', $participant->getKey())
            ->when(! $includeArchived, fn ($query) => $query->whereNull('mp.archived_at'))
            ->when($starredOnly, fn ($query) => $query->whereNotNull('mp.starred_at'))
            ->where(function ($query) use ($conversations) {
                // Hide cleared conversations until a newer message arrives.
                $query->whereNull('mp.cleared_at')
                    ->orWhereColumn('mp.cleared_at', '<', "{$conversations}.last_message_at");
            })
            ->orderByDesc("{$conversations}.last_message_at")
            ->orderByDesc("{$conversations}.id")
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->with([$participantsRelation, 'lastMessage'])
            ->select("{$conversations}.*")
            ->get();
    }
}
