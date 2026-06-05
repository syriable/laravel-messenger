<?php

namespace Syriable\Messenger\Livewire;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Syriable\Messenger\Contracts\CurrentParticipantResolver;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Contracts\ParticipantPresenter;
use Syriable\Messenger\Contracts\PresenceResolver;
use Syriable\Messenger\Exceptions\MessengerException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Support\Models;

/**
 * The conversation thread island (Epic E4). Renders a conversation's visible
 * messages (respecting the participant's clear boundary), bottom-anchored with
 * newest last, and loads older pages on demand via the domain's keyset cursor
 * (before_id). Opening a conversation marks it read.
 *
 * Messages are held as scalar view-models so Livewire state stays light. All
 * data access goes through the {@see Messenger} public API; membership is
 * enforced before anything is shown.
 */
class Thread extends Component
{
    public ?string $conversationId = null;

    /** @var array<int, array<string, mixed>> */
    public array $messages = [];

    public bool $hasMoreOlder = false;

    public int $perPage = 30;

    /** The other participant's display name while they are typing (ephemeral). */
    public ?string $typingName = null;

    /** Active tab: "messages" | "saved". */
    public string $tab = 'messages';

    /** Id of the first unread message, for the "new messages" divider. */
    public ?string $newDividerBeforeId = null;

    public int $newDividerCount = 0;

    public function mount(?string $conversationId = null): void
    {
        $this->perPage = (int) config('messenger.ui.per_page', 30);

        if ($conversationId !== null) {
            $this->open($conversationId);
        }
    }

    #[On('conversation-selected')]
    public function open(string $conversationId): void
    {
        $me = $this->participant();
        $conversation = $this->resolveConversation($conversationId, $me);

        if (! $conversation || ! $me) {
            $this->conversationId = null;
            $this->messages = [];
            $this->hasMoreOlder = false;

            return;
        }

        $this->conversationId = $conversation->id;
        // Capture the unread boundary BEFORE marking read, so the divider lands
        // above the first message received since the participant last read.
        $mine = $conversation->participantFor($me);
        $this->loadLatest($conversation, $me);
        $this->computeUnreadDivider($mine);

        Messenger::markAsRead($conversation, $me);
        $this->dispatch('conversation-read', conversationId: $conversation->id);
    }

    /**
     * Position the "new messages" divider before the first inbound message that
     * arrived after the participant's previous read point.
     */
    protected function computeUnreadDivider(Participant $mine): void
    {
        $this->newDividerBeforeId = null;
        $this->newDividerCount = 0;

        $count = (int) $mine->unread_count;

        if ($count === 0) {
            return;
        }

        $boundary = $mine->last_read_at;

        foreach ($this->messages as $message) {
            $newerThanBoundary = $boundary === null || Carbon::parse($message['time']) > $boundary;

            if (! ($message['is_self'] ?? false) && $newerThanBoundary) {
                $this->newDividerBeforeId = $message['id'];
                $this->newDividerCount = $count;

                return;
            }
        }
    }

    /**
     * Append messages newer than the last loaded one (after a send or, later, a
     * realtime event), keeping scroll position. Reloads the latest page when the
     * thread is empty.
     */
    #[On('message-sent')]
    public function appendNew(?string $conversationId = null): void
    {
        if ($this->conversationId === null || $conversationId !== $this->conversationId) {
            return;
        }

        $me = $this->participant();
        $conversation = $this->resolveConversation($this->conversationId, $me);

        if (! $conversation || ! $me) {
            return;
        }

        if ($this->messages === []) {
            $this->loadLatest($conversation, $me);
        } else {
            $otherReadAt = $this->otherReadAt($conversation, $me);
            $new = Messenger::messages($conversation, $me, [
                'after_id' => $this->messages[array_key_last($this->messages)]['id'],
            ]);
            $appended = $new->map(fn (Message $message) => $this->toViewModel($message, $me, $otherReadAt))->all();
            $this->messages = array_merge($this->messages, $appended);

            if ($appended !== []) {
                // Signal the scroll handler to auto-scroll (if at bottom) or
                // bump the "new messages below" badge on the scroll-to-bottom FAB.
                $this->dispatch('messages-appended');
            }
        }

        Messenger::markAsRead($conversation, $me);
    }

    protected function loadLatest(Conversation $conversation, MessengerParticipant $me): void
    {
        $otherReadAt = $this->otherReadAt($conversation, $me);
        $page = Messenger::messages($conversation, $me, ['limit' => $this->perPage]);
        $this->hasMoreOlder = $page->count() === $this->perPage;
        $this->messages = $page->map(fn (Message $message) => $this->toViewModel($message, $me, $otherReadAt))->all();
    }

    public function loadOlder(): void
    {
        if ($this->conversationId === null || $this->messages === []) {
            return;
        }

        $me = $this->participant();
        $conversation = $this->resolveConversation($this->conversationId, $me);

        if (! $conversation || ! $me) {
            return;
        }

        $older = Messenger::messages($conversation, $me, [
            'limit' => $this->perPage,
            'before_id' => $this->messages[0]['id'],
        ]);

        $this->hasMoreOlder = $older->count() === $this->perPage;
        $otherReadAt = $this->otherReadAt($conversation, $me);
        $mapped = $older->map(fn (Message $message) => $this->toViewModel($message, $me, $otherReadAt))->all();
        $this->messages = array_merge($mapped, $this->messages);
    }

    /**
     * Identity for the thread header (the other participant).
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function header(): ?array
    {
        $me = $this->participant();
        $conversation = $this->conversationId ? $this->resolveConversation($this->conversationId, $me) : null;

        if (! $conversation || ! $me) {
            return null;
        }

        $otherModel = $conversation->otherParticipantFor($me)?->participant;
        $other = $otherModel instanceof MessengerParticipant ? $otherModel : null;
        $presenter = app(ParticipantPresenter::class);
        $presence = app(PresenceResolver::class);

        return [
            'name' => $other ? $presenter->displayName($other) : __('messenger::ui.unknown_participant'),
            'avatar' => $other ? $presenter->avatarUrl($other) : null,
            'handle' => $other ? $presenter->handle($other) : null,
            'status' => $other ? $presence->status($other) : 'offline',
            'last_seen' => $other ? optional($presence->lastSeenAt($other))->diffForHumans() : null,
        ];
    }

    /**
     * Show / clear the other participant's typing indicator. Driven by an
     * ephemeral realtime whisper (no persistence); a no-op fallback otherwise.
     */
    public function showTyping(?string $name = null): void
    {
        $this->typingName = $name ?: ($this->header['name'] ?? null);
    }

    public function clearTyping(): void
    {
        $this->typingName = null;
    }

    /**
     * Polling entry point used when realtime broadcasting is unavailable; pulls
     * any messages newer than the last loaded one.
     */
    public function poll(): void
    {
        if ($this->conversationId !== null) {
            $this->appendNew($this->conversationId);
        }
    }

    /**
     * Per-participant state of the open conversation, for the header menu labels.
     *
     * @return array{starred: bool, archived: bool, blocked: bool}
     */
    #[Computed]
    public function state(): array
    {
        $me = $this->participant();
        $conversation = $this->conversationId ? $this->resolveConversation($this->conversationId, $me) : null;
        $mine = ($conversation && $me) ? $conversation->participantFor($me) : null;

        return [
            'starred' => (bool) $mine?->starred_at,
            'archived' => (bool) $mine?->archived_at,
            'blocked' => (bool) $mine?->blocked_at,
        ];
    }

    public function toggleStar(): void
    {
        $this->withConversation(function (Conversation $conversation, MessengerParticipant $me): void {
            $this->state()['starred']
                ? Messenger::unstar($conversation, $me)
                : Messenger::star($conversation, $me);
        });
    }

    public function toggleArchive(): void
    {
        $this->withConversation(function (Conversation $conversation, MessengerParticipant $me): void {
            $this->state()['archived']
                ? Messenger::unarchive($conversation, $me)
                : Messenger::archive($conversation, $me);
        });
    }

    public function toggleBlock(): void
    {
        $this->withConversation(function (Conversation $conversation, MessengerParticipant $me): void {
            $this->state()['blocked']
                ? Messenger::unblock($conversation, $me)
                : Messenger::block($conversation, $me);
        });
    }

    public function markUnread(): void
    {
        $this->withConversation(function (Conversation $conversation, MessengerParticipant $me): void {
            Messenger::markAsUnread($conversation, $me);
        });
    }

    public function clearChat(): void
    {
        $this->withConversation(function (Conversation $conversation, MessengerParticipant $me): void {
            Messenger::clear($conversation, $me);
            $this->loadLatest($conversation, $me);
        });
    }

    public function moveToSpam(): void
    {
        $this->withConversation(function (Conversation $conversation, MessengerParticipant $me): void {
            Messenger::spam($conversation, $me);
        });
    }

    /**
     * Enter reply mode for a message by handing the composer a quoted preview.
     */
    public function requestReply(string $messageId): void
    {
        $preview = null;

        foreach ($this->messages as $message) {
            if ($message['id'] === $messageId) {
                $preview = $message['body'];
                break;
            }
        }

        $this->dispatch('reply-requested', messageId: $messageId, preview: $preview);
    }

    public function report(string $messageId, ?string $reason = null): void
    {
        $this->withConversation(function (Conversation $conversation, MessengerParticipant $me) use ($messageId, $reason): void {
            $message = Models::message()::query()
                ->where('conversation_id', $conversation->id)
                ->whereKey($messageId)
                ->first();

            if ($message !== null) {
                Messenger::report($message, $me, $reason);
                $this->dispatch('message-reported', messageId: $messageId);
            }
        });
    }

    public function switchTab(string $tab): void
    {
        $this->tab = in_array($tab, ['messages', 'saved'], true) ? $tab : 'messages';
    }

    /**
     * Per-message reaction summaries (emoji, count, viewer-reacted) for the
     * loaded messages, recomputed each render so toggles stay live.
     *
     * @return array<string, array<int, array{emoji: string, count: int, reacted: bool}>>
     */
    #[Computed]
    public function reactionSummaries(): array
    {
        $me = $this->participant();

        if (! $me || $this->messages === []) {
            return [];
        }

        return Messenger::reactionsFor(array_column($this->messages, 'id'), $me);
    }

    public function react(string $messageId, string $emoji): void
    {
        $me = $this->participant();
        $conversation = $this->conversationId ? $this->resolveConversation($this->conversationId, $me) : null;

        if (! $conversation || ! $me) {
            return;
        }

        $message = Models::message()::query()
            ->where('conversation_id', $conversation->id)
            ->whereKey($messageId)
            ->first();

        if ($message === null) {
            return;
        }

        try {
            Messenger::react($message, $me, $emoji);
        } catch (MessengerException) {
            return;
        }

        unset($this->reactionSummaries);
    }

    public function toggleSave(string $messageId): void
    {
        $me = $this->participant();
        $conversation = $this->conversationId ? $this->resolveConversation($this->conversationId, $me) : null;

        if (! $conversation || ! $me) {
            return;
        }

        $message = Models::message()::query()
            ->where('conversation_id', $conversation->id)
            ->whereKey($messageId)
            ->first();

        if ($message === null) {
            return;
        }

        Messenger::isSaved($message, $me)
            ? Messenger::unsave($message, $me)
            : Messenger::save($message, $me);

        unset($this->savedIds, $this->savedRows);
    }

    /**
     * Ids of the open conversation's messages this participant has saved, for
     * rendering the Save/Unsave affordance on each row.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function savedIds(): array
    {
        $me = $this->participant();

        if (! $me || ! $this->conversationId) {
            return [];
        }

        return Messenger::saved($me, ['conversation_id' => $this->conversationId])
            ->pluck('id')
            ->all();
    }

    /**
     * View-models for the "Saved" tab — saved messages in this conversation.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function savedRows(): array
    {
        $me = $this->participant();

        if (! $me || ! $this->conversationId) {
            return [];
        }

        return Messenger::saved($me, ['conversation_id' => $this->conversationId])
            ->map(fn (Message $message) => $this->toViewModel($message, $me))
            ->all();
    }

    /**
     * Run a per-participant conversation action, then refresh the sidebar and
     * the composer (block/spam toggles its locked state) via one event.
     *
     * @param  \Closure(Conversation, MessengerParticipant): void  $callback
     */
    protected function withConversation(\Closure $callback): void
    {
        $me = $this->participant();
        $conversation = $this->conversationId ? $this->resolveConversation($this->conversationId, $me) : null;

        if (! $conversation || ! $me) {
            return;
        }

        $callback($conversation, $me);

        unset($this->state);
        $this->dispatch('conversation-updated', conversationId: $conversation->id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function toViewModel(Message $message, MessengerParticipant $me, ?CarbonInterface $otherReadAt = null): array
    {
        $presenter = app(ParticipantPresenter::class);
        $sender = $message->sender;
        $sender = $sender instanceof MessengerParticipant ? $sender : null;

        $isSelf = $message->sender_type === $me->getMorphClass()
            && (string) $message->sender_id === (string) $me->getKey();

        // Read status only applies to the viewer's own messages: "read" once the
        // recipient's last_read_at reaches the message, otherwise "sent".
        $status = null;
        if ($isSelf) {
            $status = ($otherReadAt !== null && $message->created_at <= $otherReadAt) ? 'read' : 'sent';
        }

        return [
            'id' => $message->id,
            'body' => $message->body,
            'time' => $message->created_at,
            'is_self' => $isSelf,
            'status' => $status,
            'sender_name' => $sender ? $presenter->displayName($sender) : __('messenger::ui.unknown_participant'),
            'sender_avatar' => $sender ? $presenter->avatarUrl($sender) : null,
            'reply_to' => $this->replyViewModel($message),
            'attachments' => $message->attachments->map(fn ($attachment) => [
                'name' => $attachment->name,
                'url' => $attachment->url,
                'is_image' => str_starts_with((string) $attachment->mime_type, 'image/'),
            ])->all(),
        ];
    }

    /**
     * The other participant's last-read timestamp, for read-receipt rendering.
     */
    protected function otherReadAt(Conversation $conversation, MessengerParticipant $me): ?CarbonInterface
    {
        return $conversation->otherParticipantFor($me)?->last_read_at;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function replyViewModel(Message $message): ?array
    {
        $reply = $message->replyTo;

        if ($reply === null) {
            return null;
        }

        return ['snippet' => Str::limit((string) $reply->body, 80) ?: __('messenger::ui.attachment')];
    }

    protected function resolveConversation(string $conversationId, ?MessengerParticipant $me): ?Conversation
    {
        if (! $me) {
            return null;
        }

        $conversation = Models::conversation()::query()
            ->with('participants')
            ->whereKey($conversationId)
            ->first();

        if (! $conversation || ! $conversation->participantFor($me)) {
            return null;
        }

        return $conversation;
    }

    protected function participant(): ?MessengerParticipant
    {
        return app(CurrentParticipantResolver::class)->resolve();
    }

    public function render()
    {
        return view('messenger::livewire.thread');
    }
}
