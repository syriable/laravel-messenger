@props([
    'message', // view-model array from the Thread component
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
        </div>

        @if (! empty($message['reply_to']))
            <div class="msgr-message__reply">{{ $message['reply_to']['snippet'] }}</div>
        @endif

        @if (! empty($message['body']))
            <div class="msgr-message__body">{{ $message['body'] }}</div>
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
    </div>
</div>
