@props([
    'src' => null,
    'name' => '',
    'size' => 40,
    'presence' => null, // online | away | offline | null
    'verified' => false,
])

@php
    $initial = trim($name) !== '' ? mb_strtoupper(mb_substr(trim($name), 0, 1)) : '?';
@endphp

<span
    {{ $attributes->merge(['class' => 'msgr-avatar']) }}
    style="inline-size: {{ (int) $size }}px; block-size: {{ (int) $size }}px; font-size: {{ (int) ($size / 2.4) }}px;"
    @if ($name !== '') title="{{ $name }}" @endif
>
    @if ($src)
        <img src="{{ $src }}" alt="{{ $name }}" loading="lazy">
    @else
        <span aria-hidden="true">{{ $initial }}</span>
        <span class="msgr-sr-only">{{ $name }}</span>
    @endif

    @if ($presence)
        <span class="msgr-presence msgr-presence--{{ $presence }}" aria-label="{{ __('messenger::ui.presence.'.$presence) }}"></span>
    @endif
</span>
