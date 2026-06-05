<?php

namespace Syriable\Messenger\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Filament\Resources\MessageReportResource\Pages\ListMessageReports;
use Syriable\Messenger\Models\MessageReport;
use Syriable\Messenger\Support\Models;

/**
 * Moderation queue for reported messages. Read-mostly: operators triage reports
 * and act on the underlying conversation through the domain API (block / spam),
 * or dismiss the report. Resolves the (config-swappable) report model.
 */
class MessageReportResource extends Resource
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static string|\UnitEnum|null $navigationGroup = 'Messaging';

    public static function getModel(): string
    {
        return Models::report();
    }

    public static function getModelLabel(): string
    {
        return __('messenger::ui.moderation.report');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messenger::ui.moderation.reports');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['message', 'reporter']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reporter_type')
                    ->label(__('messenger::ui.moderation.reporter'))
                    ->formatStateUsing(fn (MessageReport $record): string => class_basename((string) $record->reporter_type).' #'.$record->reporter_id),
                TextColumn::make('message.body')
                    ->label(__('messenger::ui.moderation.message'))
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->label(__('messenger::ui.moderation.reason'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('messenger::ui.moderation.reported_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('block')
                    ->label(__('messenger::ui.menu.block'))
                    ->icon('heroicon-o-no-symbol')
                    ->requiresConfirmation()
                    ->action(fn (MessageReport $record) => static::blockConversation($record)),
                Action::make('spam')
                    ->label(__('messenger::ui.menu.spambox'))
                    ->icon('heroicon-o-shield-exclamation')
                    ->requiresConfirmation()
                    ->action(fn (MessageReport $record) => static::spamConversation($record)),
                DeleteAction::make()
                    ->label(__('messenger::ui.moderation.dismiss')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessageReports::route('/'),
        ];
    }

    /**
     * Operator override: block the reported conversation. Block is mutual, so
     * acting on behalf of one participant stops the conversation for both.
     */
    public static function blockConversation(MessageReport $record): void
    {
        $conversation = $record->message?->conversation;
        $participant = $conversation?->participants()->first()?->participant;

        if ($conversation !== null && $participant instanceof MessengerParticipant) {
            Messenger::block($conversation, $participant);
        }
    }

    /**
     * Operator override: mark the reported conversation as spam (mutual).
     */
    public static function spamConversation(MessageReport $record): void
    {
        $conversation = $record->message?->conversation;
        $participant = $conversation?->participants()->first()?->participant;

        if ($conversation !== null && $participant instanceof MessengerParticipant) {
            Messenger::spam($conversation, $participant);
        }
    }
}
