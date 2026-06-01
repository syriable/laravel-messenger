<?php

namespace Syriable\Messenger\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Syriable\Messenger\Contracts\CurrentParticipantResolver;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Exceptions\MessengerException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Support\Models;

/**
 * The message composer island (Epic E5). Sends text and/or attachments to the
 * other participant of the active conversation, supports replying to a message,
 * disables itself when the conversation is blocked/spammed, and announces
 * `message-sent` so the Thread and Sidebar refresh.
 *
 * Sending goes through the domain send pipeline (validation, block/spam,
 * content, attachment and reply rules); pipeline violations surface as field
 * errors rather than exceptions.
 */
class Composer extends Component
{
    use WithFileUploads;

    public ?string $conversationId = null;

    public string $body = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    public ?string $replyToId = null;

    public ?string $replyPreview = null;

    public int $maxLength = 20000;

    public function mount(?string $conversationId = null): void
    {
        $this->maxLength = (int) (config('messenger.messages.max_body_length') ?? 20000);
        $this->conversationId = $conversationId;
    }

    #[On('conversation-selected')]
    public function setConversation(string $conversationId): void
    {
        $this->conversationId = $conversationId;
        $this->reset('body', 'attachments', 'replyToId', 'replyPreview');
        $this->resetErrorBag();
    }

    #[On('reply-requested')]
    public function reply(string $messageId, ?string $preview = null): void
    {
        $this->replyToId = $messageId;
        $this->replyPreview = $preview;
    }

    public function cancelReply(): void
    {
        $this->replyToId = null;
        $this->replyPreview = null;
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function send(): void
    {
        $me = $this->participant();
        $conversation = $this->conversation();

        if (! $me || ! $conversation || $this->locked()) {
            return;
        }

        $recipient = $conversation->otherParticipantFor($me)?->participant;

        if (! $recipient instanceof MessengerParticipant) {
            return;
        }

        $this->validate([
            'body' => ['nullable', 'string', 'max:'.$this->maxLength],
            'attachments' => ['array'],
            'attachments.*' => ['file'],
        ]);

        $body = trim($this->body);

        if ($body === '' && $this->attachments === []) {
            $this->addError('body', __('messenger::ui.composer.empty'));

            return;
        }

        try {
            Messenger::send($me, $recipient, [
                'body' => $body !== '' ? $body : null,
                'attachments' => $this->attachments,
                'reply_to' => $this->replyToId,
            ]);
        } catch (MessengerException $e) {
            $this->addError('body', $e->getMessage());

            return;
        }

        $this->reset('body', 'attachments', 'replyToId', 'replyPreview');

        $this->dispatch('message-sent', conversationId: $conversation->id);
    }

    /**
     * Whether sending is blocked (either side has blocked or marked as spam).
     */
    #[Computed]
    public function locked(): bool
    {
        $conversation = $this->conversation();

        if (! $conversation) {
            return true;
        }

        return $conversation->participants
            ->contains(fn ($participant) => $participant->blocked_at !== null || $participant->spammed_at !== null);
    }

    protected function conversation(): ?Conversation
    {
        $me = $this->participant();

        if (! $me || ! $this->conversationId) {
            return null;
        }

        $conversation = Models::conversation()::query()
            ->with('participants')
            ->whereKey($this->conversationId)
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
        return view('messenger::livewire.composer');
    }
}
