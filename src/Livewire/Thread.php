<?php

namespace Syriable\Messenger\Livewire;

use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Syriable\Messenger\Contracts\CurrentParticipantResolver;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Contracts\ParticipantPresenter;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
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
        $this->loadLatest($conversation, $me);

        Messenger::markAsRead($conversation, $me);
        $this->dispatch('conversation-read', conversationId: $conversation->id);
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
            $new = Messenger::messages($conversation, $me, [
                'after_id' => $this->messages[array_key_last($this->messages)]['id'],
            ]);
            $this->messages = array_merge(
                $this->messages,
                $new->map(fn (Message $message) => $this->toViewModel($message, $me))->all(),
            );
        }

        Messenger::markAsRead($conversation, $me);
    }

    protected function loadLatest(Conversation $conversation, MessengerParticipant $me): void
    {
        $page = Messenger::messages($conversation, $me, ['limit' => $this->perPage]);
        $this->hasMoreOlder = $page->count() === $this->perPage;
        $this->messages = $page->map(fn (Message $message) => $this->toViewModel($message, $me))->all();
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
        $mapped = $older->map(fn (Message $message) => $this->toViewModel($message, $me))->all();
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

        return [
            'name' => $other ? $presenter->displayName($other) : __('messenger::ui.unknown_participant'),
            'avatar' => $other ? $presenter->avatarUrl($other) : null,
            'handle' => $other ? $presenter->handle($other) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function toViewModel(Message $message, MessengerParticipant $me): array
    {
        $presenter = app(ParticipantPresenter::class);
        $sender = $message->sender;
        $sender = $sender instanceof MessengerParticipant ? $sender : null;

        $isSelf = $message->sender_type === $me->getMorphClass()
            && (string) $message->sender_id === (string) $me->getKey();

        return [
            'id' => $message->id,
            'body' => $message->body,
            'time' => $message->created_at,
            'is_self' => $isSelf,
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
