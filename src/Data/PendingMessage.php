<?php

namespace Syriable\Messenger\Data;

use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Models\Conversation;

/**
 * Mutable carrier threaded through the send-message pipeline.
 *
 * It bundles the sender, the recipient, the intended message and — once
 * resolved — the conversation the message belongs to. Pipes may inspect and
 * enrich it; the SendMessageAction reads the final state to persist.
 */
class PendingMessage
{
    public function __construct(
        public readonly MessengerParticipant $sender,
        public readonly MessengerParticipant $recipient,
        public readonly NewMessage $message,
        public ?Conversation $conversation = null,
    ) {}
}
