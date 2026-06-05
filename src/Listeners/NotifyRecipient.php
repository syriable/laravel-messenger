<?php

namespace Syriable\Messenger\Listeners;

use Syriable\Messenger\Events\MessageSent;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Notifications\NewMessageNotification;

/**
 * Notifies the recipient of a new message. Only registered when
 * `messenger.notifications.enabled` is true, so the package functions fully
 * without notifications.
 *
 * Muting is a host concern: a recipient model may opt out of a given message by
 * implementing `shouldReceiveMessengerNotification(Message): bool`. Recipients
 * that are not Notifiable are skipped.
 */
class NotifyRecipient
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        $recipientRow = $message->conversation->participants()->get()
            ->first(fn (Participant $row) => ! (
                $row->participant_type === $message->sender_type
                && (string) $row->participant_id === (string) $message->sender_id
            ));

        $recipient = $recipientRow?->participant;

        if ($recipient === null || ! method_exists($recipient, 'notify')) {
            return;
        }

        if (method_exists($recipient, 'shouldReceiveMessengerNotification')
            && ! $recipient->shouldReceiveMessengerNotification($message)) {
            return;
        }

        $recipient->notify(new NewMessageNotification($message));
    }
}
