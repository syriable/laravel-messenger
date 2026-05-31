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
 * Supported options: include_archived (bool), starred (bool), unread (bool),
 * only_spam (bool), limit (?int), with_participant_models (bool),
 * exclude_blocked (bool), exclude_spam (bool).
 * with_participant_models eager-loads the polymorphic model behind each
 * Participant row (e.g. the User) so the host can render names and avatars
 * without an N+1 (#70). exclude_blocked / exclude_spam drop conversations the
 * viewer has blocked or marked as spam (kept visible by default per the v1
 * spec, #82). unread restricts the result to conversations the viewer has
 * unread messages in (the denormalised unread_count counter, so no message
 * scan); only_spam isolates the viewer's spam folder (conversations they have
 * marked as spam) — the inverse of exclude_spam. These two back the inbox
 * filter dropdown (All / Unread / Starred / Archived / Spam).
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
        $unreadOnly = (bool) ($options['unread'] ?? false);
        $spamOnly = (bool) ($options['only_spam'] ?? false);
        $excludeBlocked = (bool) ($options['exclude_blocked'] ?? false);
        $excludeSpam = (bool) ($options['exclude_spam'] ?? false);
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
            ->when($unreadOnly, fn ($query) => $query->where('mp.unread_count', '>', 0))
            ->when($spamOnly, fn ($query) => $query->whereNotNull('mp.spammed_at'))
            ->when($excludeBlocked, fn ($query) => $query->whereNull('mp.blocked_at'))
            ->when($excludeSpam, fn ($query) => $query->whereNull('mp.spammed_at'))
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
