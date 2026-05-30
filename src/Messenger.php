<?php

namespace Syriable\Messenger;

use Illuminate\Support\Collection;
use Syriable\Messenger\Actions\ArchiveConversationAction;
use Syriable\Messenger\Actions\BlockConversationAction;
use Syriable\Messenger\Actions\ClearConversationAction;
use Syriable\Messenger\Actions\MarkConversationAsReadAction;
use Syriable\Messenger\Actions\MarkConversationAsUnreadAction;
use Syriable\Messenger\Actions\PruneAttachmentsAction;
use Syriable\Messenger\Actions\ReportMessageAction;
use Syriable\Messenger\Actions\SendMessageAction;
use Syriable\Messenger\Actions\SpamConversationAction;
use Syriable\Messenger\Actions\StarConversationAction;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Data\NewMessage;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Models\MessageReport;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Queries\FindConversationBetweenQuery;
use Syriable\Messenger\Queries\GetConversationMessagesQuery;
use Syriable\Messenger\Queries\GetInboxConversationsQuery;
use Syriable\Messenger\Queries\GetUnreadCountQuery;

/**
 * The package's primary entry point — a thin facade over the actions and
 * queries layer. All business logic lives in those classes; this service only
 * wires them together for a pleasant, discoverable API.
 */
class Messenger
{
    /**
     * Send a message from one participant to another. The conversation is
     * created automatically on the first message.
     *
     * @param  string|array<string, mixed>|NewMessage  $message
     */
    public function send(MessengerParticipant $from, MessengerParticipant $to, string|array|NewMessage $message): Message
    {
        return app(SendMessageAction::class)->execute($from, $to, $message);
    }

    /**
     * The single conversation between two participants, if one exists.
     */
    public function between(MessengerParticipant $a, MessengerParticipant $b): ?Conversation
    {
        return app(FindConversationBetweenQuery::class)->execute($a, $b);
    }

    /**
     * A participant's inbox, ordered by latest activity.
     *
     * @return Collection<int, Conversation>
     */
    public function inbox(MessengerParticipant $participant, array $options = []): Collection
    {
        return app(GetInboxConversationsQuery::class)->execute($participant, $options);
    }

    /**
     * The visible messages of a conversation for a participant, chronologically.
     *
     * Options: limit (?int), and the mutually-exclusive keyset cursors
     * before_id / after_id for scroll-up / load-newer pagination on large
     * conversations.
     *
     * @return Collection<int, Message>
     */
    public function messages(Conversation $conversation, MessengerParticipant $participant, array $options = []): Collection
    {
        return app(GetConversationMessagesQuery::class)->execute($conversation, $participant, $options);
    }

    public function unreadCount(MessengerParticipant $participant, bool $includeArchived = false): int
    {
        return app(GetUnreadCountQuery::class)->execute($participant, $includeArchived);
    }

    public function unreadConversations(MessengerParticipant $participant, bool $includeArchived = false): int
    {
        return app(GetUnreadCountQuery::class)->conversations($participant, $includeArchived);
    }

    public function archive(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        return app(ArchiveConversationAction::class)->execute($conversation, $participant);
    }

    public function unarchive(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        return app(ArchiveConversationAction::class)->undo($conversation, $participant);
    }

    public function star(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        return app(StarConversationAction::class)->execute($conversation, $participant);
    }

    public function unstar(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        return app(StarConversationAction::class)->undo($conversation, $participant);
    }

    public function block(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        return app(BlockConversationAction::class)->execute($conversation, $participant);
    }

    public function unblock(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        return app(BlockConversationAction::class)->undo($conversation, $participant);
    }

    public function spam(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        return app(SpamConversationAction::class)->execute($conversation, $participant);
    }

    public function unspam(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        return app(SpamConversationAction::class)->undo($conversation, $participant);
    }

    public function clear(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        return app(ClearConversationAction::class)->execute($conversation, $participant);
    }

    public function markAsRead(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        return app(MarkConversationAsReadAction::class)->execute($conversation, $participant);
    }

    public function markAsUnread(Conversation $conversation, MessengerParticipant $participant): Participant
    {
        return app(MarkConversationAsUnreadAction::class)->execute($conversation, $participant);
    }

    public function report(Message $message, MessengerParticipant $reporter, ?string $reason = null, ?string $note = null): MessageReport
    {
        return app(ReportMessageAction::class)->execute($message, $reporter, $reason, $note);
    }

    /**
     * Garbage-collect orphaned attachment files (on disk but with no matching
     * database row). Returns the orphaned paths (deleted unless $dryRun).
     *
     * @return Collection<int, string>
     */
    public function pruneAttachments(?string $disk = null, bool $dryRun = false): Collection
    {
        return app(PruneAttachmentsAction::class)->execute($disk, $dryRun);
    }
}
