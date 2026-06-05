<?php

namespace Syriable\Messenger\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Syriable\Messenger\Listeners\NotifyRecipient;
use Syriable\Messenger\Models\Message;

/**
 * Notifies a participant of a new message. Channels are configured via
 * `messenger.notifications.channels` (default: database). Extend or replace this
 * class — and the {@see NotifyRecipient} listener —
 * for richer payloads, mail formatting or queueing.
 */
class NewMessageNotification extends Notification
{
    public function __construct(
        public readonly Message $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return (array) config('messenger.notifications.channels', ['database']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'conversation_id' => $this->message->conversation_id,
            'message_id' => $this->message->getKey(),
            'sender_type' => $this->message->sender_type,
            'sender_id' => $this->message->sender_id,
            'preview' => Str::limit((string) $this->message->body, 140),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
