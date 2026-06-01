<?php

namespace Syriable\Messenger\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The full-page messenger root (Epic E2/E3). Composes the Sidebar, Thread and
 * Composer islands into the 3-column shell and owns the URL-addressable
 * conversation selection, so a conversation is deep-linkable and the browser
 * back button works. The islands coordinate among themselves via events; this
 * root only tracks which conversation is open.
 */
#[Layout('messenger::layouts.app')]
class Messenger extends Component
{
    #[Url(as: 'c', history: true)]
    public ?string $conversation = null;

    public function mount(?string $conversation = null): void
    {
        if ($conversation !== null) {
            $this->conversation = $conversation;
        }
    }

    #[On('conversation-selected')]
    public function onConversationSelected(string $conversationId): void
    {
        $this->conversation = $conversationId;
    }

    public function render()
    {
        return view('messenger::livewire.messenger');
    }
}
