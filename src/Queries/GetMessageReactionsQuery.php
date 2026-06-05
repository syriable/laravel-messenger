<?php

namespace Syriable\Messenger\Queries;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Models\MessageReaction;
use Syriable\Messenger\Support\Models;

/**
 * Builds per-message reaction summaries (emoji, count, whether the viewer
 * reacted) for a set of messages in a single query, ready for the UI.
 */
class GetMessageReactionsQuery
{
    /**
     * @param  array<int, string>  $messageIds
     * @return array<string, array<int, array{emoji: string, count: int, reacted: bool}>>
     */
    public function forMessages(array $messageIds, MessengerParticipant $viewer): array
    {
        if ($messageIds === []) {
            return [];
        }

        $rows = Models::reaction()::query()
            ->whereIn('message_id', $messageIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $summaries = [];

        foreach ($rows as $reaction) {
            /** @var MessageReaction $reaction */
            $messageId = (string) $reaction->message_id;
            $emoji = $reaction->emoji;

            if (! isset($summaries[$messageId][$emoji])) {
                $summaries[$messageId][$emoji] = ['emoji' => $emoji, 'count' => 0, 'reacted' => false];
            }

            $summaries[$messageId][$emoji]['count']++;

            if ($reaction->participant_type === $viewer->getMorphClass()
                && (string) $reaction->participant_id === (string) $viewer->getKey()) {
                $summaries[$messageId][$emoji]['reacted'] = true;
            }
        }

        // Drop the emoji keys so the UI gets a plain ordered list per message.
        return array_map('array_values', $summaries);
    }
}
