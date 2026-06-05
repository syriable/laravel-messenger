@props([
    'message', // view-model array from the Thread component
    'saved' => false,
    'reactions' => [],
])

<div @class([
    'msgr-message',
    'msgr-message--self' => $message['is_self'] ?? false,
])>
    <x-messenger::avatar :name="$message['sender_name']" :src="$message['sender_avatar'] ?? null" :size="36" />

    <div class="msgr-message__main">
        <div class="msgr-message__meta">
            <span class="msgr-message__sender">
                {{ ($message['is_self'] ?? false) ? __('messenger::ui.you') : $message['sender_name'] }}
            </span>
            <x-messenger::timestamp :value="$message['time'] ?? null" :relative="false" />

            <div class="msgr-message__menu" x-data="{ open: false }">
                <button type="button" class="msgr-iconbtn" @click="open = ! open" :aria-expanded="open" aria-haspopup="menu" aria-label="{{ __('messenger::ui.menu.message') }}">&ctdot;</button>
                <div class="msgr-menu" role="menu" x-show="open" x-cloak @click.outside="open = false">
                    <button type="button" role="menuitem" wire:click="requestReply('{{ $message['id'] }}')" @click="open = false">{{ __('messenger::ui.menu.reply') }}</button>
                    <button type="button" role="menuitem" wire:click="toggleSave('{{ $message['id'] }}')" @click="open = false">{{ $saved ? __('messenger::ui.menu.unsave') : __('messenger::ui.menu.save') }}</button>
                    <button type="button" role="menuitem" wire:click="report('{{ $message['id'] }}')" @click="open = false">{{ __('messenger::ui.menu.report') }}</button>
                    <button type="button" role="menuitem" class="msgr-menu__danger" wire:click="moveToSpam" @click="open = false">{{ __('messenger::ui.menu.spambox') }}</button>
                </div>
            </div>
        </div>

        @if (! empty($message['reply_to']))
            <div class="msgr-message__reply">{{ $message['reply_to']['snippet'] }}</div>
        @endif

        @if (! empty($message['body']))
            <div class="msgr-message__body">{{ $message['body'] }}</div>
        @endif

        @if (! empty($message['status']))
            <span class="msgr-message__receipt msgr-message__receipt--{{ $message['status'] }}">
                {{ $message['status'] === 'read' ? __('messenger::ui.receipt.read') : __('messenger::ui.receipt.sent') }}
            </span>
        @endif

        @if (! empty($message['attachments']))
            <div class="msgr-message__attachments">
                @foreach ($message['attachments'] as $attachment)
                    @if ($attachment['is_image'])
                        <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener">
                            <img src="{{ $attachment['url'] }}" alt="{{ $attachment['name'] }}" class="msgr-attach__img" loading="lazy">
                        </a>
                    @else
                        <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener" class="msgr-attach__file">
                            {{ $attachment['name'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        @endif

        @php $allowedEmoji = (array) config('messenger.reactions.allowed', []); @endphp
        <div class="msgr-message__reactions">
            @foreach ($reactions as $reaction)
                <button
                    type="button"
                    wire:click="react('{{ $message['id'] }}', @js($reaction['emoji']))"
                    @class(['msgr-reaction', 'msgr-reaction--on' => $reaction['reacted'] ?? false])
                    aria-pressed="{{ ($reaction['reacted'] ?? false) ? 'true' : 'false' }}"
                >
                    <span aria-hidden="true">{{ $reaction['emoji'] }}</span>
                    <span class="msgr-reaction__count">{{ $reaction['count'] }}</span>
                </button>
            @endforeach

            @if ($allowedEmoji)
                <div class="msgr-reaction-add" x-data="{ open: false }">
                    <button type="button" class="msgr-iconbtn" @click="open = ! open" :aria-expanded="open" aria-haspopup="menu" aria-label="{{ __('messenger::ui.reactions.add') }}">+</button>
                    <div class="msgr-menu msgr-reaction-menu" role="menu" x-show="open" x-cloak @click.outside="open = false">
                        @foreach ($allowedEmoji as $emoji)
                            <button type="button" wire:click="react('{{ $message['id'] }}', @js($emoji))" @click="open = false">{{ $emoji }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
