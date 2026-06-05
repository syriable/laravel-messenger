<?php

namespace Syriable\Messenger\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Enums\Width;

/**
 * Hosts the full messenger UI (`<livewire:messenger />`) inside a Filament panel,
 * full-width. The page is a thin wrapper — all behaviour lives in the Livewire
 * components and the headless domain.
 */
class ChatPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Messaging';

    protected static ?string $slug = 'messages';

    protected string $view = 'messenger::filament.chat';

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return __('messenger::ui.conversations');
    }

    public static function getNavigationLabel(): string
    {
        return __('messenger::ui.conversations');
    }
}
