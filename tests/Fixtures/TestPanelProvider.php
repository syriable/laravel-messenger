<?php

namespace Syriable\Messenger\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Syriable\Messenger\Filament\MessengerPlugin;

/**
 * Minimal Filament panel used to exercise the messenger moderation plugin.
 */
class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(MessengerPlugin::make());
    }
}
