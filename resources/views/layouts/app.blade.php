<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('messenger::ui.conversations') }}</title>

    {{-- Bundled design tokens & base styles. Publish with
         `php artisan vendor:publish --tag="messenger-assets"`. --}}
    <link rel="stylesheet" href="{{ asset('vendor/messenger/messenger.css') }}">

    @livewireStyles
</head>
<body style="margin: 0; block-size: 100vh;">
    {{ $slot }}

    @livewireScripts
</body>
</html>
