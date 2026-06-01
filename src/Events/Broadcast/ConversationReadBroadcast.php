<?php

namespace Syriable\Messenger\Events\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Syriable\Messenger\Listeners\BroadcastConversationRead;
use Syriable\Messenger\Models\Participant;

/**
 * Broadcastable projection of a read receipt. Emitted by
 * {@see BroadcastConversationRead} only when broadcasting is enabled, so the
 * sender's UI can flip their delivered messages to "read" live. Carries scalar
 * fields only; the database remains authoritative.
 */
class ConversationReadBroadcast implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Participant $participant,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $name = config('messenger.broadcasting.channel_prefix', 'messenger')
            .'.conversation.'.$this->participant->conversation_id;

        return [
            config('messenger.broadcasting.private', true)
                ? new PrivateChannel($name)
                : new Channel($name),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->participant->conversation_id,
            'participant_type' => $this->participant->participant_type,
            'participant_id' => $this->participant->participant_id,
            'read_at' => optional($this->participant->last_read_at)->toIso8601String(),
        ];
    }
}
