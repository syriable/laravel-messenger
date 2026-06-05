<?php

namespace Syriable\Messenger\Queries;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Contracts\ParticipantSearchResolver;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Support\Limit;
use Syriable\Messenger\Support\Models;

/**
 * Searches a participant's inbox: returns the conversations (ordered by latest
 * activity, like the inbox) that match a free-text term either by the other
 * participant's name/handle (via the {@see ParticipantSearchResolver}) or by any
 * message body in the conversation.
 *
 * Body matching is a case-insensitive LIKE; name matching is delegated to the
 * host resolver. The clear boundary and the default archived exclusion are
 * honoured, so search never surfaces something the plain inbox would hide.
 *
 * Supported options: include_archived (bool), with_participant_models (bool),
 * limit (?int).
 *
 * @return Collection<int, Conversation>
 */
class SearchInboxQuery
{
    public function execute(MessengerParticipant $participant, string $term, array $options = []): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        $conversations = Models::newConversation()->getTable();
        $participants = Models::newParticipant()->getTable();
        $messages = Models::newMessage()->getTable();

        $includeArchived = (bool) ($options['include_archived'] ?? false);
        $withParticipantModels = (bool) ($options['with_participant_models'] ?? false);
        $limit = Limit::normalize($options['limit'] ?? null);

        /** @var Collection<int, MessengerParticipant> $matches */
        $matches = app(ParticipantSearchResolver::class)->search($term);

        $participantsRelation = $withParticipantModels ? 'participants.participant' : 'participants';
        $like = '%'.mb_strtolower($term).'%';

        return Models::conversation()::query()
            ->join("{$participants} as mp", 'mp.conversation_id', '=', "{$conversations}.id")
            ->where('mp.participant_type', $participant->getMorphClass())
            ->where('mp.participant_id', $participant->getKey())
            ->when(! $includeArchived, fn ($query) => $query->whereNull('mp.archived_at'))
            ->where(function ($query) use ($conversations) {
                $query->whereNull('mp.cleared_at')
                    ->orWhereColumn('mp.cleared_at', '<', "{$conversations}.last_message_at");
            })
            ->where(function ($query) use ($matches, $participant, $participants, $messages, $conversations, $like) {
                // (a) the other participant's name/handle matched (host-resolved).
                if ($matches->isNotEmpty()) {
                    $query->whereExists(function (QueryBuilder $sub) use ($matches, $participant, $participants, $conversations) {
                        $sub->selectRaw('1')
                            ->from("{$participants} as mp2")
                            ->whereColumn('mp2.conversation_id', "{$conversations}.id")
                            ->where(function (QueryBuilder $other) use ($participant) {
                                // the matched participant must not be the viewer
                                $other->where('mp2.participant_type', '!=', $participant->getMorphClass())
                                    ->orWhere('mp2.participant_id', '!=', $participant->getKey());
                            })
                            ->where(function (QueryBuilder $in) use ($matches) {
                                foreach ($matches as $match) {
                                    $in->orWhere(function (QueryBuilder $pair) use ($match) {
                                        $pair->where('mp2.participant_type', $match->getMorphClass())
                                            ->where('mp2.participant_id', $match->getKey());
                                    });
                                }
                            });
                    });
                }

                // (b) any message body in the conversation matched.
                $query->orWhereExists(function (QueryBuilder $sub) use ($messages, $conversations, $like) {
                    $sub->selectRaw('1')
                        ->from("{$messages} as msrch")
                        ->whereColumn('msrch.conversation_id', "{$conversations}.id")
                        ->whereRaw('lower(msrch.body) like ?', [$like]);
                });
            })
            ->orderByDesc("{$conversations}.last_message_at")
            ->orderByDesc("{$conversations}.id")
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->with([$participantsRelation, 'lastMessage'])
            ->select("{$conversations}.*")
            ->get();
    }
}
