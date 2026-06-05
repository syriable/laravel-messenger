@props([
    'variant' => 'default', // default | accent
])

<span {{ $attributes->merge(['class' => 'msgr-badge msgr-badge--'.$variant]) }}>
    {{ $slot }}
</span>
