<div class="msgr-thread__inner">
    @if ($conversationId && $this->header)
        <header class="msgr-thread__header">
            <x-messenger::avatar :name="$this->header['name']" :src="$this->header['avatar']" :size="40" />
            <div class="msgr-thread__identity">
                <span class="msgr-thread__name">{{ $this->header['name'] }}</span>
                @if ($this->header['handle'])
                    <span class="msgr-thread__handle">{{ '@'.ltrim($this->header['handle'], '@') }}</span>
                @endif
            </div>
        </header>

        <div class="msgr-thread__messages" role="log" aria-live="polite">
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
    @else
        <x-messenger::empty-state />
    @endif
</div>
