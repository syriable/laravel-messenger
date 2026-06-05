<?php

namespace Syriable\Messenger\Livewire;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Syriable\Messenger\Contracts\CurrentParticipantResolver;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Contracts\ParticipantPresenter;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;

/**
 * The conversation-list rail (Epic E3). Reads the current participant's inbox
 * through the domain's public query layer, resolves display identity via the
 * {@see ParticipantPresenter}, and lets the user filter by scope and search.
 *
 * Selecting a row dispatches `conversation-selected` for the Thread island to
 * react to; the Sidebar itself only owns list state. All data access goes
 * through {@see Messenger}, never the database directly.
 */
class Sidebar extends Component
{
    /** Inbox scope: all | unread | starred | archived | spam. */
    #[Url(as: 'filter', history: true)]
    public string $scope = 'all';

    #[Url(as: 'q', history: true)]
    public string $search = '';

    public ?string $activeConversationId = null;

    /** Filter scopes offered in the header dropdown. */
    public const SCOPES = ['all', 'unread', 'starred', 'archived', 'spam'];

    public function setScope(string $scope): void
    {
        $this->scope = in_array($scope, self::SCOPES, true) ? $scope : 'all';
    }

    public function select(string $conversationId): void
    {
        $this->activeConversationId = $conversationId;

        $this->dispatch('conversation-selected', conversationId: $conversationId);
    }

    /**
     * Re-render the list (recomputing the inbox) when activity changes elsewhere
     * — a sent message or a read receipt. The computed `rows` is rebuilt on the
     * resulting request, so ordering, snippets and unread badges stay fresh.
     */
    #[On('message-sent')]
    #[On('conversation-read')]
    #[On('conversation-updated')]
    public function refresh(): void {}

    /**
     * The rendered conversation rows, newest activity first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function rows(): Collection
    {
        $me = $this->participant();

        if (! $me) {
            return collect();
        }

        $presenter = app(ParticipantPresenter::class);
        $term = trim($this->search);

        // A search term runs a global server-side search (message bodies +
        // participant names via the host resolver); otherwise the scoped inbox.
        $conversations = $term !== ''
            ? Messenger::searchInbox($me, $term, ['with_participant_models' => true, 'include_archived' => true])
            : Messenger::inbox($me, $this->options());

        return $conversations
            ->map(fn (Conversation $conversation) => $this->toViewModel($conversation, $me, $presenter))
            ->values();
    }

    /**
     * Inbox query options for the active scope.
     *
     * @return array<string, mixed>
     */
    protected function options(): array
    {
        $options = ['with_participant_models' => true];

        return match ($this->scope) {
            'unread' => $options + ['unread' => true],
            'starred' => $options + ['starred' => true],
            'archived' => $options + ['include_archived' => true],
            'spam' => $options + ['only_spam' => true],
            default => $options,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function toViewModel(Conversation $conversation, MessengerParticipant $me, ParticipantPresenter $presenter): array
    {
        $mine = $conversation->participantFor($me);
        $otherModel = $conversation->otherParticipantFor($me)?->participant;
        $other = $otherModel instanceof MessengerParticipant ? $otherModel : null;
        $last = $conversation->lastMessage;

        $lastIsMine = $last
            && $last->sender_type === $me->getMorphClass()
            && (string) $last->sender_id === (string) $me->getKey();

        return [
            'id' => $conversation->id,
            'name' => $other ? $presenter->displayName($other) : __('messenger::ui.unknown_participant'),
            'avatar' => $other ? $presenter->avatarUrl($other) : null,
            'snippet' => $last?->body,
            'is_self_last' => (bool) $lastIsMine,
            'time' => $conversation->last_message_at,
            'unread' => (int) $mine->unread_count,
            'starred' => (bool) $mine->starred_at,
            'active' => $this->activeConversationId === $conversation->id,
        ];
    }

    protected function participant(): ?MessengerParticipant
    {
        return app(CurrentParticipantResolver::class)->resolve();
    }

    public function render()
    {
        return view('messenger::livewire.sidebar');
    }
}
