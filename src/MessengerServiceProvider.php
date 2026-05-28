<?php

namespace Syriable\Messenger;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Messenger\Commands\MessengerCommand;

class MessengerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-messenger')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_messenger_table')
            ->hasCommand(MessengerCommand::class);
    }
}
