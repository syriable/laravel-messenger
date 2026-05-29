<?php

use Illuminate\Support\Facades\Event;
use Syriable\Messenger\Actions\BlockConversationAction;
use Syriable\Messenger\Events\ConversationBlocked;
use Syriable\Messenger\Events\ConversationUnblocked;
use Syriable\Messenger\Exceptions\ConversationBlockedException;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Tests\Models\User;

it('blocks a conversation and sets blocked_at', function () {
    Event::fake([ConversationBlocked::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);

    $row = Messenger::block($conversation, $alice);

    expect($row->isBlocking())->toBeTrue()
        ->and($row->blocked_at)->not->toBeNull();

    Event::assertDispatched(ConversationBlocked::class, fn ($e) => $e->participant->is($row));
});

it('prevents the blocker from sending', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);
    Messenger::block($conversation, $alice);

    expect(fn () => Messenger::send($alice, $bob, 'still here?'))
        ->toThrow(ConversationBlockedException::class);
});

it('blocks mutually so the blocked party also cannot send', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);
    Messenger::block($conversation, $alice);

    expect(fn () => Messenger::send($bob, $alice, 'reply'))
        ->toThrow(ConversationBlockedException::class);
});

it('keeps conversation history stored and retrievable while blocked', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'first');
    Messenger::send($bob, $alice, 'second');

    $conversation = Messenger::between($alice, $bob);
    Messenger::block($conversation, $alice);

    $messages = Messenger::messages($conversation, $alice);

    expect($messages)->toHaveCount(2)
        ->and($messages->pluck('body')->all())->toBe(['first', 'second']);
});

it('re-enables sending after unblock', function () {
    Event::fake([ConversationUnblocked::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);
    Messenger::block($conversation, $alice);

    $row = Messenger::unblock($conversation, $alice);
    expect($row->isBlocking())->toBeFalse()
        ->and($row->blocked_at)->toBeNull();

    $message = Messenger::send($alice, $bob, 'we are back');
    expect($message->body)->toBe('we are back');

    Event::assertDispatched(ConversationUnblocked::class, fn ($e) => $e->participant->is($row));
});

it('throws InvalidParticipantException when blocking for a non-member', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $stranger = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);

    expect(fn () => app(BlockConversationAction::class)->execute($conversation, $stranger))
        ->toThrow(InvalidParticipantException::class);
});
