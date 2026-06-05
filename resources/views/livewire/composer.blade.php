<div class="msgr-composer__inner">
    @if ($conversationId)
        @if ($this->locked)
            <div class="msgr-composer__locked">{{ __('messenger::ui.composer.locked') }}</div>
        @else
            @if ($replyToId)
                <div class="msgr-composer__reply">
                    <span class="msgr-composer__reply-text">{{ $replyPreview ?? __('messenger::ui.composer.replying') }}</span>
                    <button type="button" wire:click="cancelReply" aria-label="{{ __('messenger::ui.composer.cancel_reply') }}">&times;</button>
                </div>
            @endif

            @error('body') <div class="msgr-composer__error">{{ $message }}</div> @enderror
            @error('attachments.*') <div class="msgr-composer__error">{{ $message }}</div> @enderror

            @if ($attachments)
                <div class="msgr-composer__chips">
                    @foreach ($attachments as $index => $file)
                        <span class="msgr-chip" wire:key="att-{{ $index }}">
                            {{ method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : __('messenger::ui.attachment') }}
                            <button type="button" wire:click="removeAttachment({{ $index }})" aria-label="{{ __('messenger::ui.composer.remove_attachment') }}">&times;</button>
                        </span>
                    @endforeach
                </div>
            @endif

            <form wire:submit="send" class="msgr-composer__form">
                <textarea
                    wire:model="body"
                    class="msgr-composer__input"
                    rows="1"
                    maxlength="{{ $maxLength }}"
                    placeholder="{{ __('messenger::ui.composer.placeholder') }}"
                    aria-label="{{ __('messenger::ui.composer.placeholder') }}"
                    x-data
                    x-on:keydown.enter="
                        if ($wire.enterToSend && ! $event.shiftKey) { $event.preventDefault(); $wire.send(); }
                        else if (! $wire.enterToSend && ($event.metaKey || $event.ctrlKey)) { $event.preventDefault(); $wire.send(); }
                    "
                    @if (config('messenger.broadcasting.enabled'))
                        @php $channel = config('messenger.broadcasting.channel_prefix', 'messenger').'.conversation.'.$conversationId; @endphp
                        x-on:input.throttle.2000ms="window.Echo && window.Echo.{{ config('messenger.broadcasting.private', true) ? 'private' : 'channel' }}(@js($channel)).whisper('typing', {})"
                    @endif
                ></textarea>

                <label class="msgr-composer__attach" title="{{ __('messenger::ui.composer.attach') }}">
                    <span aria-hidden="true">📎</span>
                    <input type="file" wire:model="attachments" multiple hidden>
                    <span class="msgr-sr-only">{{ __('messenger::ui.composer.attach') }}</span>
                </label>

                <div class="msgr-composer__enter" x-data="{ open: false }">
                    <button type="button" class="msgr-iconbtn" @click="open = ! open" :aria-expanded="open" aria-haspopup="menu" aria-label="{{ __('messenger::ui.composer.enter_behaviour') }}">&dtrif;</button>
                    <div class="msgr-menu" role="menu" x-show="open" x-cloak @click.outside="open = false">
                        <button type="button" role="menuitemradio" :aria-checked="@js($enterToSend)" wire:click="setEnterToSend(true)" @click="open = false" @class(['msgr-menu__active' => $enterToSend])>
                            {{ __('messenger::ui.composer.enter_send') }}
                        </button>
                        <button type="button" role="menuitemradio" :aria-checked="@js(! $enterToSend)" wire:click="setEnterToSend(false)" @click="open = false" @class(['msgr-menu__active' => ! $enterToSend])>
                            {{ __('messenger::ui.composer.enter_newline') }}
                        </button>
                    </div>
                </div>

                <button type="submit" class="msgr-composer__send" wire:loading.attr="disabled">
                    {{ __('messenger::ui.composer.send') }}
                </button>
            </form>
        @endif
    @endif
</div>
