@props([
    'status' => 'offline', // online | away | offline
])

<span
    {{ $attributes->merge(['class' => 'msgr-presence msgr-presence--'.$status]) }}
    style="position: static; border: 0;"
    role="img"
    aria-label="{{ __('messenger::ui.presence.'.$status) }}"
></span>
