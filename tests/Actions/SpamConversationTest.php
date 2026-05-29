<?php

use Illuminate\Support\Facades\Event;
use Syriable\Messenger\Actions\SpamConversationAction;
use Syriable\Messenger\Events\ConversationMarkedAsSpam;
use Syriable\Messenger\Events\ConversationUnmarkedAsSpam;
use Syriable\Messenger\Exceptions\ConversationBlockedException;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Tests\Models\User;

it('marks a conversation as spam and sets spammed_at', function () {
    Event::fake([ConversationMarkedAsSpam::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);

    $row = Messenger::spam($conversation, $alice);

    expect($row->isSpamming())->toBeTrue()
        ->and($row->spammed_at)->not->toBeNull();

    Event::assertDispatched(ConversationMarkedAsSpam::class, fn ($e) => $e->participant->is($row));
});

it('blocks sending for both participants while spammed (v1 spam behaves like block)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);
    Messenger::spam($conversation, $alice);

    expect(fn () => Messenger::send($alice, $bob, 'a'))
        ->toThrow(ConversationBlockedException::class);
    expect(fn () => Messenger::send($bob, $alice, 'b'))
        ->toThrow(ConversationBlockedException::class);
});

it('preserves history while spammed', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'first');

    $conversation = Messenger::between($alice, $bob);
    Messenger::spam($conversation, $bob);

    expect(Messenger::messages($conversation, $bob))->toHaveCount(1);
});

it('unmarks spam and re-enables sending', function () {
    Event::fake([ConversationUnmarkedAsSpam::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);
    Messenger::spam($conversation, $alice);

    $row = Messenger::unspam($conversation, $alice);
    expect($row->isSpamming())->toBeFalse()
        ->and($row->spammed_at)->toBeNull();

    $message = Messenger::send($alice, $bob, 'unspammed');
    expect($message->body)->toBe('unspammed');

    Event::assertDispatched(ConversationUnmarkedAsSpam::class, fn ($e) => $e->participant->is($row));
});

it('throws InvalidParticipantException when spamming for a non-member', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $stranger = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);

    expect(fn () => app(SpamConversationAction::class)->execute($conversation, $stranger))
        ->toThrow(InvalidParticipantException::class);
});
