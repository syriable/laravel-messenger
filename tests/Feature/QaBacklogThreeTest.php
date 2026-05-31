<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Events\Broadcast\MessageSentBroadcast;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Queries\GetInboxConversationsQuery;
use Syriable\Messenger\Tests\Models\User;

/*
 * #77 — the published config stub must expose every documented option, so an
 * integrator has a complete reference after vendor:publish without reading source.
 */
it('ships every documented option in the config stub', function () {
    $config = require __DIR__.'/../../config/messenger.php';

    expect($config['validation'])->toHaveKey('verify_participants_exist')
        ->and($config['validation']['verify_participants_exist'])->toBeFalse()
        ->and($config['reports'])->toHaveKey('participants_only')
        ->and($config['reports']['participants_only'])->toBeFalse()
        ->and($config['attachments'])->toHaveKey('allow_empty')
        ->and($config['attachments']['allow_empty'])->toBeFalse();
});

/*
 * #82 — blocked/spam threads stay in the inbox by default, but can be excluded.
 */
it('keeps blocked and spam conversations in the inbox by default', function () {
    $me = User::factory()->create();
    $blocker = User::factory()->create();
    $spammer = User::factory()->create();
    $normal = User::factory()->create();

    Messenger::send($me, $blocker, 'hi');
    Messenger::send($me, $spammer, 'hi');
    Messenger::send($me, $normal, 'hi');

    Messenger::block(Messenger::between($me, $blocker), $me);
    Messenger::spam(Messenger::between($me, $spammer), $me);

    $inbox = app(GetInboxConversationsQuery::class)->execute($me);

    expect($inbox)->toHaveCount(3);
});

it('drops blocked and/or spam conversations when asked', function () {
    $me = User::factory()->create();
    $blocker = User::factory()->create();
    $spammer = User::factory()->create();
    $normal = User::factory()->create();

    Messenger::send($me, $blocker, 'hi');
    Messenger::send($me, $spammer, 'hi');
    Messenger::send($me, $normal, 'hi');

    Messenger::block(Messenger::between($me, $blocker), $me);
    Messenger::spam(Messenger::between($me, $spammer), $me);

    expect(app(GetInboxConversationsQuery::class)->execute($me, ['exclude_blocked' => true]))->toHaveCount(2)
        ->and(app(GetInboxConversationsQuery::class)->execute($me, ['exclude_spam' => true]))->toHaveCount(2)
        ->and(app(GetInboxConversationsQuery::class)->execute($me, [
            'exclude_blocked' => true,
            'exclude_spam' => true,
        ]))->toHaveCount(1);
});

/*
 * #81 — the broadcast payload carries a metadata-only attachment summary.
 */
it('includes a metadata-only attachment summary in the broadcast payload', function () {
    Storage::fake('local');

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $message = Messenger::send($alice, $bob, [
        'body' => 'see attached',
        'attachments' => [UploadedFile::fake()->image('photo.jpg', 10, 10)],
    ]);

    $payload = (new MessageSentBroadcast($message))->broadcastWith();

    expect($payload['has_attachments'])->toBeTrue()
        ->and($payload['attachments'])->toHaveCount(1)
        ->and($payload['attachments'][0])->toHaveKeys(['id', 'name', 'mime_type', 'size'])
        ->and($payload['attachments'][0]['name'])->toBe('photo.jpg')
        // No file contents, path or URL leak into the broadcast.
        ->and($payload['attachments'][0])->not->toHaveKeys(['path', 'disk', 'url']);
});

it('reports no attachments for a body-only message', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $message = Messenger::send($alice, $bob, 'just text');

    $payload = (new MessageSentBroadcast($message))->broadcastWith();

    expect($payload['has_attachments'])->toBeFalse()
        ->and($payload['attachments'])->toBe([]);
});
