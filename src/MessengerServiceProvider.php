<?php

namespace Syriable\Messenger;

use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Messenger\Events\MessageSent;
use Syriable\Messenger\Listeners\BroadcastMessageSent;

class MessengerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * A headless, backend-only messaging domain platform.
         *
         * https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-messenger')
            ->hasConfigFile()
            ->discoversMigrations()
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Messenger::class);
    }

    public function packageBooted(): void
    {
        if (config('messenger.broadcasting.enabled', false)) {
            Event::listen(MessageSent::class, BroadcastMessageSent::class);
        }
    }
}
