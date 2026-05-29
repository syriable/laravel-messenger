<?php

namespace Syriable\Messenger\Actions;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Data\NewMessage;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Events\ConversationCreated;
use Syriable\Messenger\Events\MessageSent;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Queries\FindConversationBetweenQuery;
use Syriable\Messenger\Services\AttachmentService;
use Syriable\Messenger\Support\ConversationKey;
use Syriable\Messenger\Support\Models;

/**
 * Sends a message from one participant to another.
 *
 * The conversation is created lazily on the first message (conversations are
 * never empty). The message passes through the configurable send pipeline
 * before being persisted alongside its attachments. Denormalised fields
 * (conversation activity, unread counters) are updated in the same
 * transaction, and domain events are dispatched.
 */
class SendMessageAction
{
    public function __construct(
        private readonly AttachmentService $attachments,
        private readonly FindConversationBetweenQuery $findConversation,
    ) {}

    /**
     * @param  string|array<string, mixed>|NewMessage  $message
     */
    public function execute(
        MessengerParticipant $sender,
        MessengerParticipant $recipient,
        string|array|NewMessage $message,
    ): Message {
        $pending = new PendingMessage(
            sender: $sender,
            recipient: $recipient,
            message: NewMessage::from($message),
            conversation: $this->findConversation->execute($sender, $recipient),
        );

        /** @var PendingMessage $pending */
        $pending = app(Pipeline::class)
            ->send($pending)
            ->through(config('messenger.pipeline', []))
            ->via('handle')
            ->thenReturn();

        return DB::transaction(function () use ($pending) {
            $created = $pending->conversation === null;

            $conversation = $pending->conversation ?? $this->createConversation($pending);

            if ($created) {
                ConversationCreated::dispatch($conversation);
            }

            $sentMessage = $this->persistMessage($conversation, $pending);

            $this->updateProjections($conversation, $sentMessage, $pending);

            MessageSent::dispatch($sentMessage);

            return $sentMessage;
        });
    }

    private function createConversation(PendingMessage $pending): Conversation
    {
        /** @var Conversation $conversation */
        $conversation = Models::conversation()::query()->create([
            'key' => ConversationKey::for($pending->sender, $pending->recipient),
        ]);

        $conversation->setRelation('participants', $conversation->participants()->createMany([
            $this->participantAttributes($pending->sender),
            $this->participantAttributes($pending->recipient),
        ]));

        return $conversation;
    }

    /**
     * @return array<string, mixed>
     */
    private function participantAttributes(MessengerParticipant $participant): array
    {
        return [
            'participant_type' => $participant->getMorphClass(),
            'participant_id' => $participant->getKey(),
        ];
    }

    private function persistMessage(Conversation $conversation, PendingMessage $pending): Message
    {
        /** @var Message $message */
        $message = $conversation->messages()->create([
            'sender_type' => $pending->sender->getMorphClass(),
            'sender_id' => $pending->sender->getKey(),
            'body' => $pending->message->body,
            'reply_to_id' => $pending->message->replyToId,
        ]);

        foreach ($pending->message->attachments as $file) {
            $message->attachments()->create($this->attachments->store($file)->toAttributes());
        }

        return $message->load('attachments');
    }

    private function updateProjections(Conversation $conversation, Message $message, PendingMessage $pending): void
    {
        $conversation->forceFill([
            'last_message_id' => $message->getKey(),
            'last_message_at' => $message->created_at,
        ])->save();

        $sender = $conversation->participantFor($pending->sender);
        $recipient = $conversation->participantFor($pending->recipient);

        // The sender implicitly reads the conversation by sending; sending also
        // surfaces an archived conversation back into the inbox.
        $sender?->forceFill([
            'last_read_at' => $message->created_at,
            'unread_count' => 0,
            'archived_at' => null,
        ])->save();

        // The recipient gains one unread message and the conversation resurfaces.
        $recipient?->forceFill([
            'unread_count' => $recipient->unread_count + 1,
            'archived_at' => null,
        ])->save();
    }
}
