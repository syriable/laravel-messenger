<?php

use Illuminate\Support\ServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Syriable\Messenger\MessengerServiceProvider;

// Issue #43 — publish tags follow the Spatie short-name convention (messenger-*),
// not the full package name. Guard the documented tags against regression.
it('publishes migrations under the messenger-migrations tag', function () {
    $paths = ServiceProvider::pathsToPublish(MessengerServiceProvider::class, 'messenger-migrations');

    expect($paths)->not->toBeEmpty()
        ->and(collect($paths)->keys()->implode("\n"))->toContain('create_messenger_conversations_table');
});

it('publishes the config under the messenger-config tag', function () {
    $paths = ServiceProvider::pathsToPublish(MessengerServiceProvider::class, 'messenger-config');

    expect(collect($paths)->values()->implode("\n"))->toContain('messenger.php');
});

it('does not register the mistaken laravel-messenger-* tags', function () {
    expect(ServiceProvider::pathsToPublish(MessengerServiceProvider::class, 'laravel-messenger-migrations'))->toBeEmpty()
        ->and(ServiceProvider::pathsToPublish(MessengerServiceProvider::class, 'laravel-messenger-config'))->toBeEmpty();
});

// Issue #44 — migrations ship as .php.stub and are publish-only; the package
// must not claim to run them automatically (Laravel's migrator ignores stubs).
it('does not auto-run migrations (publish-only workflow)', function () {
    $provider = new MessengerServiceProvider(app());
    $package = new Package;
    $provider->configurePackage($package);

    expect($package->runsMigrations)->toBeFalse()
        ->and($package->discoversMigrations)->toBeTrue();
});
