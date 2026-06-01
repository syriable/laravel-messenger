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
        </header>

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
                <x-messenger::message-row :message="$message" wire:key="msg-{{ $message['id'] }}" />
            @endforeach
        </div>

        @if ($typingName)
            <div class="msgr-thread__typing" aria-live="polite">
                {{ __('messenger::ui.typing', ['name' => $typingName]) }}
            </div>
        @endif
    @else
        <x-messenger::empty-state />
    @endif
</div>
