@props([
    'row', // view-model array from the Sidebar component
])

<div @class([
    'msgr-item',
    'msgr-item--active' => $row['active'] ?? false,
    'msgr-item--unread' => ($row['unread'] ?? 0) > 0,
])>
    <x-messenger::avatar :name="$row['name']" :src="$row['avatar'] ?? null" :size="48" />

    <div class="msgr-item__body">
        <div class="msgr-item__row">
            <span class="msgr-item__name">{{ $row['name'] }}</span>
            <x-messenger::timestamp :value="$row['time'] ?? null" class="msgr-item__time" />
        </div>

        <div class="msgr-item__row">
            <span class="msgr-item__snippet">
                @if ($row['is_self_last'] ?? false)
                    <span class="msgr-item__me">{{ __('messenger::ui.you_prefix') }}</span>
                @endif
                {{ \Illuminate\Support\Str::limit($row['snippet'] ?? '', 48) ?: __('messenger::ui.no_messages_yet') }}
            </span>

            <x-messenger::unread-counter :count="$row['unread'] ?? 0" />
        </div>
    </div>
</div>
