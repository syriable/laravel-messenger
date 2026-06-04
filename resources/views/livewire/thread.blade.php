<div class="msgr-thread__inner">
    @if ($conversationId && $this->header)
        <header class="msgr-thread__header">
            <x-messenger::avatar :name="$this->header['name']" :src="$this->header['avatar']" :size="40" :presence="$this->header['status']" />
            <div class="msgr-thread__identity">
                <span class="msgr-thread__name">{{ $this->header['name'] }}</span>
                <span class="msgr-thread__status">
                    @if ($this->header['status'] === 'online')
                        {{ __('messenger::ui.presence.online') }}
                    @elseif ($this->header['last_seen'])
                        {{ __('messenger::ui.last_seen', ['time' => $this->header['last_seen']]) }}
                    @elseif ($this->header['handle'])
                        {{ '@'.ltrim($this->header['handle'], '@') }}
                    @endif
                </span>
            </div>

            <div class="msgr-thread__actions" x-data="{ open: false }">
                <button type="button" class="msgr-iconbtn" @click="open = ! open" :aria-expanded="open" aria-haspopup="menu" aria-label="{{ __('messenger::ui.menu.conversation') }}">&ctdot;</button>
                <div class="msgr-menu" role="menu" x-show="open" x-cloak @click.outside="open = false" wire:loading.remove>
                    <button type="button" role="menuitem" wire:click="toggleStar" @click="open = false">
                        {{ $this->state['starred'] ? __('messenger::ui.unstar') : __('messenger::ui.star') }}
                    </button>
                    <button type="button" role="menuitem" wire:click="markUnread" @click="open = false">{{ __('messenger::ui.menu.mark_unread') }}</button>
                    <button type="button" role="menuitem" wire:click="toggleArchive" @click="open = false">
                        {{ $this->state['archived'] ? __('messenger::ui.menu.unarchive') : __('messenger::ui.menu.archive') }}
                    </button>
                    <button type="button" role="menuitem" wire:click="toggleBlock" @click="open = false">
                        {{ $this->state['blocked'] ? __('messenger::ui.menu.unblock') : __('messenger::ui.menu.block') }}
                    </button>
                    <button type="button" role="menuitem" class="msgr-menu__danger" wire:click="clearChat" @click="open = false">{{ __('messenger::ui.menu.clear') }}</button>
                </div>
            </div>
        </header>

        <div class="msgr-thread__tabs" role="tablist">
            <button type="button" role="tab" wire:click="switchTab('messages')"
                @class(['msgr-tab', 'msgr-tab--active' => $tab === 'messages'])
                aria-selected="{{ $tab === 'messages' ? 'true' : 'false' }}">{{ __('messenger::ui.tab.messages') }}</button>
            <button type="button" role="tab" wire:click="switchTab('saved')"
                @class(['msgr-tab', 'msgr-tab--active' => $tab === 'saved'])
                aria-selected="{{ $tab === 'saved' ? 'true' : 'false' }}">{{ __('messenger::ui.tab.saved') }}</button>
        </div>

        @if ($tab === 'saved')
            <div class="msgr-thread__messages" role="log">
                @forelse ($this->savedRows as $message)
                    <x-messenger::message-row :message="$message" :saved="true" wire:key="saved-{{ $message['id'] }}" />
                @empty
                    <p class="msgr-rail__empty">{{ __('messenger::ui.empty.saved') }}</p>
                @endforelse
            </div>
        @else
            @include('messenger::livewire.partials.realtime')

            <div
                class="msgr-thread__messages"
                role="log"
                aria-live="polite"
                @unless (config('messenger.broadcasting.enabled'))
                    wire:poll.visible.{{ config('messenger.ui.polling.thread', '5s') }}="poll"
                @endunless
            >
                @if ($hasMoreOlder)
                    <div class="msgr-thread__more">
                        <button type="button" wire:click="loadOlder" wire:loading.attr="disabled">
                            {{ __('messenger::ui.load_earlier') }}
                        </button>
                    </div>
                @endif

                @foreach ($messages as $message)
                    <x-messenger::message-row :message="$message" :saved="in_array($message['id'], $this->savedIds, true)" wire:key="msg-{{ $message['id'] }}" />
                @endforeach
            </div>

            @if ($typingName)
                <div class="msgr-thread__typing" aria-live="polite">
                    {{ __('messenger::ui.typing', ['name' => $typingName]) }}
                </div>
            @endif
        @endif
    @else
        <x-messenger::empty-state />
    @endif
</div>
