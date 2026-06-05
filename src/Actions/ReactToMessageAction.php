<?php

namespace Syriable\Messenger\Actions;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Events\MessageReacted;
use Syriable\Messenger\Events\MessageUnreacted;
use Syriable\Messenger\Exceptions\InvalidReactionException;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Models\MessageReaction;
use Syriable\Messenger\Support\Models;

/**
 * Toggles an emoji reaction on a message for a participant: adds it if absent,
 * removes it if already present. Returns the created reaction, or null when the
 * reaction was toggled off.
 */
class ReactToMessageAction
{
    public function execute(Message $message, MessengerParticipant $participant, string $emoji): ?MessageReaction
    {
        $emoji = trim($emoji);

        if ($emoji === '') {
            throw InvalidReactionException::empty();
        }

        $allowed = (array) config('messenger.reactions.allowed', []);

        if ($allowed !== [] && ! in_array($emoji, $allowed, true)) {
            throw InvalidReactionException::notAllowed($emoji);
        }

        $existing = Models::reaction()::query()
            ->where('message_id', $message->getKey())
            ->where('participant_type', $participant->getMorphClass())
            ->where('participant_id', $participant->getKey())
            ->where('emoji', $emoji)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            MessageUnreacted::dispatch($message, $participant, $emoji);

            return null;
        }

        /** @var MessageReaction $reaction */
        $reaction = Models::reaction()::query()->create([
            'message_id' => $message->getKey(),
            'conversation_id' => $message->conversation_id,
            'participant_type' => $participant->getMorphClass(),
            'participant_id' => $participant->getKey(),
            'emoji' => $emoji,
        ]);

        MessageReacted::dispatch($reaction);

        return $reaction;
    }
}
