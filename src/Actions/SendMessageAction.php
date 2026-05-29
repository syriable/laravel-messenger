<?php

namespace Syriable\Messenger\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Data\NewMessage;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Data\StoredAttachment;
use Syriable\Messenger\Events\ConversationCreated;
use Syriable\Messenger\Events\MessageSent;
use Syriable\Messenger\Exceptions\ConversationBlockedException;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Queries\FindConversationBetweenQuery;
use Syriable\Messenger\Services\AttachmentService;
use Syriable\Messenger\Support\ConversationKey;
use Syriable\Messenger\Support\Models;
use Throwable;

/**
 * Sends a message from one participant to another.
 *
 * The conversation is created lazily on the first message (conversations are
 * never empty). The message passes through the configurable send pipeline
 * before being persisted alongside its attachments.
 *
 * Transaction & concurrency guarantees:
 *  - Attachment files are written before the transaction and removed again if
 *    it rolls back, so a failed send never leaves orphaned files on disk.
 *  - A lost lazy-creation race (two simultaneous first messages) is recovered
 *    by attaching to the conversation the winning request created, with bounded
 *    retries to cover the window before the winner's transaction is visible.
 *  - Block/spam state is re-checked inside the write transaction (with the
 *    participant rows locked) so it cannot be bypassed in the time-of-check /
 *    time-of-use gap after the pipeline ran.
 *  - The recipient unread counter is incremented atomically in SQL so parallel
 *    sends cannot lose an update.
 *  - Domain events are dispatched only after all enclosing transactions have
 *    committed, so listeners and broadcasts always observe persisted data even
 *    when the host wraps the send in its own transaction.
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

        // Store files up front so the database transaction stays short. If the
        // transaction ultimately fails, the files are cleaned up below.
        $stored = $this->storeAttachments($pending->message->attachments);

        try {
            return $this->persist($pending, $stored);
        } catch (Throwable $e) {
            $this->discardStoredAttachments($stored);

            throw $e;
        }
    }

    /**
     * Maximum attempts to recover from a lost first-message creation race
     * before giving up and rethrowing the unique-constraint violation.
     */
    protected int $maxCreateAttempts = 3;

    /**
     * @param  array<int, StoredAttachment>  $stored
     */
    protected function persist(PendingMessage $pending, array $stored): Message
    {
        $attempts = 0;

        while (true) {
            try {
                [$conversation, $sentMessage, $created] = DB::transaction(function () use ($pending, $stored) {
                    $created = $pending->conversation === null;

                    $conversation = $pending->conversation ?? $this->createConversation($pending);

                    // Re-check block/spam inside the transaction with the
                    // participant rows locked, closing the time-of-check /
                    // time-of-use gap after the pipeline ran.
                    $this->guardNotBlocked($conversation);

                    $sentMessage = $this->persistMessage($conversation, $pending, $stored);

                    $this->updateProjections($conversation, $sentMessage, $pending);

                    return [$conversation, $sentMessage, $created];
                });

                // Dispatch only after every enclosing transaction commits, so
                // listeners and broadcasts always see persisted data — even when
                // the host wraps the send in its own outer transaction.
                if ($created) {
                    DB::afterCommit(fn () => ConversationCreated::dispatch($conversation));
                }

                DB::afterCommit(fn () => MessageSent::dispatch($sentMessage));

                return $sentMessage;
            } catch (UniqueConstraintViolationException $e) {
                // Another first message created the conversation first. Re-resolve
                // it and retry, attaching this message to the same thread. The
                // winner's transaction may not be visible immediately, so retry
                // a bounded number of times with a short backoff.
                if (++$attempts >= $this->maxCreateAttempts) {
                    throw $e;
                }

                usleep(10000 * $attempts);

                $pending->conversation = $this->findConversation->execute($pending->sender, $pending->recipient);
            }
        }
    }

    protected function guardNotBlocked(Conversation $conversation): void
    {
        $blocked = $conversation->participants()
            ->lockForUpdate()
            ->get()
            ->contains(fn (Participant $row) => $row->isBlocking() || $row->isSpamming());

        if ($blocked) {
            throw ConversationBlockedException::make();
        }
    }

    protected function createConversation(PendingMessage $pending): Conversation
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
    protected function participantAttributes(MessengerParticipant $participant): array
    {
        return [
            'participant_type' => $participant->getMorphClass(),
            'participant_id' => $participant->getKey(),
        ];
    }

    /**
     * @param  array<int, StoredAttachment>  $stored
     */
    protected function persistMessage(Conversation $conversation, PendingMessage $pending, array $stored): Message
    {
        /** @var Message $message */
        $message = $conversation->messages()->create([
            'sender_type' => $pending->sender->getMorphClass(),
            'sender_id' => $pending->sender->getKey(),
            'body' => $pending->message->body,
            'reply_to_id' => $pending->message->replyToId,
        ]);

        foreach ($stored as $attachment) {
            $message->attachments()->create($attachment->toAttributes());
        }

        return $message->load('attachments');
    }

    protected function updateProjections(Conversation $conversation, Message $message, PendingMessage $pending): void
    {
        $conversation->forceFill([
            'last_message_id' => $message->getKey(),
            'last_message_at' => $message->created_at,
        ])->save();

        $sender = $conversation->participantFor($pending->sender);
        $recipient = $conversation->participantFor($pending->recipient);

        // Both participant rows must exist; a conversation missing one is
        // corrupt, and silently skipping the update would leave unread counters
        // wrong. Fail loudly so the transaction rolls back.
        if ($sender === null || $recipient === null) {
            throw InvalidParticipantException::notInConversation();
        }

        // The sender implicitly reads the conversation by sending; sending also
        // surfaces an archived conversation back into the inbox.
        $sender->forceFill([
            'last_read_at' => $message->created_at,
            'unread_count' => 0,
            'archived_at' => null,
        ])->save();

        // Increment atomically (column = column + 1) so concurrent sends cannot
        // lose an update, and resurface the conversation for the recipient.
        $recipient->increment('unread_count', 1, ['archived_at' => null]);
    }

    /**
     * @param  array<int, mixed>  $files
     * @return array<int, StoredAttachment>
     */
    protected function storeAttachments(array $files): array
    {
        return array_map(fn ($file) => $this->attachments->store($file), $files);
    }

    /**
     * @param  array<int, StoredAttachment>  $stored
     */
    protected function discardStoredAttachments(array $stored): void
    {
        foreach ($stored as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
    }
}
