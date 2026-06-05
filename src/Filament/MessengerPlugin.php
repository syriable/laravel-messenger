<?php

namespace Syriable\Messenger\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Syriable\Messenger\Filament\Resources\MessageReportResource;

/**
 * Filament plugin that registers the messenger moderation tooling on a panel.
 *
 * Thin by design: it only registers resources; all behaviour lives in the
 * headless domain (the resource actions call Messenger::*). Add it to a panel:
 *
 *     use Syriable\Messenger\Filament\MessengerPlugin;
 *
 *     $panel->plugin(MessengerPlugin::make());
 *
 * Optional and self-contained — the package works fully without Filament; these
 * classes are only loaded when a host that has Filament references the plugin.
 */
class MessengerPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'messenger';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            MessageReportResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
