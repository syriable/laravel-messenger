<?php

use Filament\Facades\Filament;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Filament\MessengerPlugin;
use Syriable\Messenger\Filament\Pages\ChatPage;
use Syriable\Messenger\Filament\Resources\MessageReportResource;
use Syriable\Messenger\Filament\Resources\MessageReportResource\Pages\ListMessageReports;
use Syriable\Messenger\Models\MessageReport;
use Syriable\Messenger\Tests\Concerns\WithFilamentPanel;
use Syriable\Messenger\Tests\Models\User;

uses(WithFilamentPanel::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('registers the plugin with the messenger id', function () {
    expect(MessengerPlugin::make()->getId())->toBe('messenger');
});

it('resolves the configured report model', function () {
    expect(MessageReportResource::getModel())->toBe(MessageReport::class);
});

it('registers the chat page and its view on the panel', function () {
    expect(Filament::getCurrentPanel()->getPages())->toContain(ChatPage::class)
        ->and(view()->exists('messenger::filament.chat'))->toBeTrue();
});

it('registers the list page route on the resource', function () {
    expect(MessageReportResource::getPages())->toHaveKey('index')
        ->and(ListMessageReports::getResource())->toBe(MessageReportResource::class);
});

it('queries reported messages (eager-loaded) for the moderation table', function () {
    $reporter = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $reporter, 'something offensive');
    $report = Messenger::report($message, $reporter, 'spam');

    $records = MessageReportResource::getEloquentQuery()->get();

    expect($records->pluck('id')->all())->toContain($report->id)
        ->and($records->firstWhere('id', $report->id)->relationLoaded('message'))->toBeTrue();
});

it('blocks the reported conversation from the moderation action', function () {
    $reporter = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $reporter, 'offensive');
    $report = Messenger::report($message, $reporter, 'spam');

    MessageReportResource::blockConversation($report);

    expect($message->conversation->participants()->whereNotNull('blocked_at')->exists())->toBeTrue();
});

it('marks the reported conversation as spam from the moderation action', function () {
    $reporter = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $reporter, 'spammy');
    $report = Messenger::report($message, $reporter, 'spam');

    MessageReportResource::spamConversation($report);

    expect($message->conversation->participants()->whereNotNull('spammed_at')->exists())->toBeTrue();
});
