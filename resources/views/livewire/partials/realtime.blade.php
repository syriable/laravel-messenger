{{--
    Realtime wiring (Epic E6). Rendered only when broadcasting is enabled and a
    conversation is open. Subscribes via Laravel Echo to the conversation channel
    for new messages, read receipts and typing whispers, calling back into the
    Livewire component. Degrades to nothing (the thread polls instead) when Echo
    is unavailable. Requires the host to have Echo configured and the published
    messenger channel routes registered.
--}}
@if (config('messenger.broadcasting.enabled') && $conversationId)
    @php
        $channel = config('messenger.broadcasting.channel_prefix', 'messenger').'.conversation.'.$conversationId;
        $accessor = config('messenger.broadcasting.private', true) ? 'private' : 'channel';
    @endphp

    <div wire:ignore x-data="{
        timer: null,
        init() {
            if (! window.Echo) { return; }

            const channel = window.Echo.{{ $accessor }}(@js($channel));

            channel.listen('.message.sent', () => $wire.appendNew(@js($conversationId)));

            channel.listenForWhisper('typing', () => {
                $wire.showTyping();
                clearTimeout(this.timer);
                this.timer = setTimeout(() => $wire.clearTyping(), 3500);
            });
        }
    }"></div>
@endif
