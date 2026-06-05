@props([
    'count' => 0,
    'max' => 99,
])

@php $count = (int) $count; @endphp

@if ($count > 0)
    <span
        {{ $attributes->merge(['class' => 'msgr-counter']) }}
        aria-label="{{ trans_choice('messenger::ui.unread_messages', $count, ['count' => $count]) }}"
    >{{ $count > $max ? $max.'+' : $count }}</span>
@endif
