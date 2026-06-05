<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Syriable\Messenger\MessengerServiceProvider;

/**
 * The UI package scaffold (Epic E2): the bundled view namespace, Blade
 * components, translations, design-token asset and publish tags. These are
 * framework-agnostic — they require no Livewire — and must not disturb the
 * headless domain.
 */
it('registers the messenger view namespace and shell', function () {
    expect(view()->exists('messenger::components.shell'))->toBeTrue()
        ->and(view()->exists('messenger::components.avatar'))->toBeTrue()
        ->and(view()->exists('messenger::components.badge'))->toBeTrue();
});

it('renders the badge component under the messenger namespace', function () {
    $html = Blade::render('<x-messenger::badge variant="accent">Pro</x-messenger::badge>');

    expect($html)->toContain('Pro')
        ->and($html)->toContain('msgr-badge')
        ->and($html)->toContain('msgr-badge--accent');
});

it('renders an avatar with an initial fallback when no image is given', function () {
    $html = Blade::render('<x-messenger::avatar name="Nancy C" />');

    expect($html)->toContain('msgr-avatar')
        ->and($html)->toContain('>N<') // first initial
        ->and($html)->toContain('Nancy C'); // sr-only / title
});

it('renders the unread counter only when the count is positive', function () {
    expect(Blade::render('<x-messenger::unread-counter :count="5" />'))->toContain('>5<')
        ->and(trim(Blade::render('<x-messenger::unread-counter :count="0" />')))->toBe('')
        ->and(Blade::render('<x-messenger::unread-counter :count="250" :max="99" />'))->toContain('99+');
});

it('renders the inbox empty state with translated defaults', function () {
    $html = Blade::render('<x-messenger::empty-state />');

    expect($html)->toContain('Pick up where you left off')
        ->and($html)->toContain('Select a conversation and chat away.');
});

it('ships translatable UI strings in english and arabic', function () {
    app()->setLocale('en');
    expect(__('messenger::ui.empty.inbox_title'))->toBe('Pick up where you left off');

    app()->setLocale('ar');
    expect(__('messenger::ui.empty.inbox_title'))->toBe('تابع من حيث توقفت');
});

it('exposes the documented UI publish tags', function () {
    expect(ServiceProvider::pathsToPublish(MessengerServiceProvider::class, 'messenger-views'))->not->toBeEmpty()
        ->and(ServiceProvider::pathsToPublish(MessengerServiceProvider::class, 'messenger-assets'))->not->toBeEmpty()
        ->and(ServiceProvider::pathsToPublish(MessengerServiceProvider::class, 'messenger-translations'))->not->toBeEmpty()
        ->and(ServiceProvider::pathsToPublish(MessengerServiceProvider::class, 'messenger-channels'))->not->toBeEmpty();
});

it('publishes the design-token stylesheet as an asset', function () {
    $assets = ServiceProvider::pathsToPublish(MessengerServiceProvider::class, 'messenger-assets');
    $source = collect($assets)->keys()->first();

    expect($source)->toContain('resources/dist')
        ->and(is_file($source.'/messenger.css'))->toBeTrue();
});

it('provides UI config defaults without affecting the headless domain', function () {
    expect(config('messenger.ui.theme'))->toBe('neutral')
        ->and(config('messenger.ui.message_style'))->toBe('flat')
        ->and(config('messenger.ui.per_page'))->toBe(30);
});
