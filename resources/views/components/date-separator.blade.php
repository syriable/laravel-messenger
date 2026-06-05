@props([
    'date' => null,
])

@php
    $carbon = $date instanceof \DateTimeInterface
        ? \Illuminate\Support\Carbon::instance($date)
        : \Illuminate\Support\Carbon::parse($date);

    $label = $carbon->isToday()
        ? __('messenger::ui.date.today')
        : ($carbon->isYesterday() ? __('messenger::ui.date.yesterday') : $carbon->isoFormat('LL'));
@endphp

<div {{ $attributes->merge(['class' => 'msgr-date-sep']) }} role="separator">
    <span>{{ $label }}</span>
</div>
