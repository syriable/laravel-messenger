@props([
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'msgr-empty']) }}>
    @if (isset($icon))
        <div class="msgr-empty__icon" aria-hidden="true">{{ $icon }}</div>
    @endif

    <p class="msgr-empty__title">{{ $title ?? __('messenger::ui.empty.inbox_title') }}</p>
    <p class="msgr-empty__subtitle">{{ $subtitle ?? __('messenger::ui.empty.inbox_subtitle') }}</p>

    {{ $slot }}
</div>
