@props([
    'starred' => false,
])

<button
    type="button"
    {{ $attributes->merge(['class' => 'msgr-star'.($starred ? ' msgr-star--on' : '')]) }}
    aria-pressed="{{ $starred ? 'true' : 'false' }}"
    aria-label="{{ $starred ? __('messenger::ui.unstar') : __('messenger::ui.star') }}"
>
    <svg width="18" height="18" viewBox="0 0 24 24" fill="{{ $starred ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" stroke-linejoin="round"/>
    </svg>
</button>
