<?php

namespace Syriable\Messenger\Events\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Syriable\Messenger\Listeners\BroadcastMessageSent;
use Syriable\Messenger\Models\Message;

/**
 * Broadcastable projection of a sent message. Emitted by
 * {@see BroadcastMessageSent} only when
 * broadcasting is enabled, keeping broadcasting fully decoupled from the
 * SendMessageAction.
 */
class MessageSentBroadcast implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Message $message,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $name = config('messenger.broadcasting.channel_prefix', 'messenger')
            .'.conversation.'.$this->message->conversation_id;

        return [
            config('messenger.broadcasting.private', true)
                ? new PrivateChannel($name)
                : new Channel($name),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // A lightweight attachment summary (metadata only — no file contents or
        // URLs) so clients can render attachment-only / mixed messages from the
        // broadcast without a follow-up request. loadMissing covers the queued
        // case where SerializesModels rehydrates the message without relations.
        $attachments = $this->message->loadMissing('attachments')->attachments;

        return [
            'id' => $this->message->getKey(),
            'conversation_id' => $this->message->conversation_id,
            'sender_type' => $this->message->sender_type,
            'sender_id' => $this->message->sender_id,
            'body' => $this->message->body,
            'reply_to_id' => $this->message->reply_to_id,
            'created_at' => optional($this->message->created_at)->toIso8601String(),
            'has_attachments' => $attachments->isNotEmpty(),
            'attachments' => $attachments->map(fn ($attachment) => [
                'id' => $attachment->getKey(),
                'name' => $attachment->name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
            ])->values()->all(),
        ];
    }
}
