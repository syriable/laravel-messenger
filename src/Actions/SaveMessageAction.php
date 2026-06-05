<?php

namespace Syriable\Messenger\Actions;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Events\MessageSaved;
use Syriable\Messenger\Events\MessageUnsaved;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Models\SavedMessage;
use Syriable\Messenger\Support\Models;

/**
 * Saves (bookmarks) or unsaves a message for a single participant. Idempotent:
 * saving twice keeps exactly one row; unsaving an unsaved message is a no-op.
 * Reporting/membership is a host concern — like reporting, saving is not gated
 * to conversation participants here.
 */
class SaveMessageAction
{
    public function execute(Message $message, MessengerParticipant $participant): SavedMessage
    {
        /** @var SavedMessage $saved */
        $saved = Models::savedMessage()::query()->updateOrCreate(
            [
                'participant_type' => $participant->getMorphClass(),
                'participant_id' => $participant->getKey(),
                'message_id' => $message->getKey(),
            ],
            [
                'conversation_id' => $message->conversation_id,
            ],
        );

        MessageSaved::dispatch($saved);

        return $saved;
    }

    public function undo(Message $message, MessengerParticipant $participant): void
    {
        $deleted = Models::savedMessage()::query()
            ->forParticipant($participant)
            ->where('message_id', $message->getKey())
            ->delete();

        if ($deleted > 0) {
            MessageUnsaved::dispatch($message, $participant);
        }
    }
}
