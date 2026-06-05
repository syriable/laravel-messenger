@props([
    'value' => null, // Carbon|DateTimeInterface|string|null
    'relative' => true,
])

@php
    $date = $value instanceof \DateTimeInterface
        ? \Illuminate\Support\Carbon::instance($value)
        : ($value ? \Illuminate\Support\Carbon::parse($value) : null);
@endphp

@if ($date)
    <time
        {{ $attributes->merge(['class' => 'msgr-message__time']) }}
        datetime="{{ $date->toIso8601String() }}"
        title="{{ $date->toDayDateTimeString() }}"
    >{{ $relative ? $date->diffForHumans(['short' => true]) : $date->format('d M, H:i') }}</time>
@endif
