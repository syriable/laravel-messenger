<?php

namespace Syriable\Messenger;

use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Messenger\Commands\InstallCommand;
use Syriable\Messenger\Commands\PruneAttachmentsCommand;
use Syriable\Messenger\Events\MessageSent;
use Syriable\Messenger\Listeners\BroadcastMessageSent;

class MessengerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * A headless, backend-only messaging domain platform.
         *
         * Migrations ship as publishable stubs (so host apps can customise table
         * names/columns before they run). Laravel's migrator does not execute
         * `.php.stub` files, so we intentionally do not call runsMigrations();
         * the migrations must be published first. See the README for the setup
         * steps.
         *
         * https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-messenger')
            ->hasConfigFile()
            ->discoversMigrations()
            ->hasCommand(PruneAttachmentsCommand::class)
            ->hasCommand(InstallCommand::class);
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
