<?php

namespace Syriable\Messenger;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Messenger\Commands\PruneAttachmentsCommand;
use Syriable\Messenger\Contracts\CurrentParticipantResolver;
use Syriable\Messenger\Contracts\ParticipantPresenter;
use Syriable\Messenger\Events\MessageSent;
use Syriable\Messenger\Listeners\BroadcastMessageSent;
use Syriable\Messenger\Livewire\Sidebar;
use Syriable\Messenger\Support\AuthParticipantResolver;
use Syriable\Messenger\Support\DefaultParticipantPresenter;

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
            ->hasViews('messenger')
            ->hasTranslations()
            ->hasAssets()
            ->discoversMigrations()
            ->hasCommand(PruneAttachmentsCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Messenger::class);

        // The participant presenter is the swappable boundary the UI uses to
        // resolve names/avatars/handles without the domain assuming host schema.
        // Hosts override it by binding the contract or pointing the config at
        // their own class; the convention-based default needs no setup.
        $this->app->bind(ParticipantPresenter::class, function ($app) {
            return $app->make($app['config']->get('messenger.presenter', DefaultParticipantPresenter::class));
        });

        // Resolves "who am I" for the UI. Defaults to the authenticated user
        // when it is a participant; hosts bind their own for impersonation,
        // multi-guard or tenant contexts.
        $this->app->bind(CurrentParticipantResolver::class, function ($app) {
            return $app->make($app['config']->get('messenger.ui.participant_resolver', AuthParticipantResolver::class));
        });
    }

    public function packageBooted(): void
    {
        // Register the bundled Blade components under the `messenger` namespace
        // so they resolve as <x-messenger::avatar />, <x-messenger::badge />,
        // etc. These are framework-agnostic presentation primitives; the
        // interactive Livewire islands build on top of them.
        Blade::anonymousComponentNamespace('messenger::components', 'messenger');

        // The broadcast channel authorization is published (not auto-loaded) so
        // the host controls how participant identity maps onto auth.
        $this->publishes([
            __DIR__.'/../routes/channels.php' => base_path('routes/messenger-channels.php'),
        ], 'messenger-channels');

        // Register the interactive Livewire components only when Livewire is
        // installed, so the headless domain has no hard dependency on it. Defer
        // to the "booted" callback so Livewire's own provider has registered
        // regardless of provider order.
        if (class_exists(Livewire::class)) {
            $this->app->booted(function () {
                Livewire::component('messenger.sidebar', Sidebar::class);
            });
        }

        if (config('messenger.broadcasting.enabled', false)) {
            Event::listen(MessageSent::class, BroadcastMessageSent::class);
        }
    }
}
