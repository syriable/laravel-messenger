<?php

use Syriable\Messenger\Events\MessageReacted;
use Syriable\Messenger\Events\MessageUnreacted;
use Syriable\Messenger\Exceptions\InvalidReactionException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Support\Models;
use Syriable\Messenger\Tests\Models\User;

/**
 * Reactions (#F1.8): a per-participant, additive emoji reaction that toggles and
 * never affects the message itself.
 */
it('adds a reaction and returns the row', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $me, 'hello');

    $reaction = Messenger::react($message, $me, '👍');

    expect($reaction)->not->toBeNull()
        ->and($reaction->emoji)->toBe('👍')
        ->and($reaction->message_id)->toBe($message->id)
        ->and($reaction->conversation_id)->toBe($message->conversation_id);
});

it('toggles the same emoji off when reacted again', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $me, 'hello');

    Messenger::react($message, $me, '👍');
    $second = Messenger::react($message, $me, '👍');

    expect($second)->toBeNull()
        ->and(Models::reaction()::query()->count())->toBe(0);
});

it('allows multiple distinct emojis from the same participant', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $me, 'hello');

    Messenger::react($message, $me, '👍');
    Messenger::react($message, $me, '❤️');

    expect(Models::reaction()::query()->count())->toBe(2);
});

it('rejects an emoji outside the allowed set', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $me, 'hello');

    Messenger::react($message, $me, '🤬');
})->throws(InvalidReactionException::class);

it('rejects an empty emoji', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $me, 'hello');

    Messenger::react($message, $me, '   ');
})->throws(InvalidReactionException::class);

it('summarises reactions with counts and the viewer flag', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $message = Messenger::send($alice, $me, 'hello');

    Messenger::react($message, $me, '👍');
    Messenger::react($message, $alice, '👍');
    Messenger::react($message, $bob, '❤️');

    $summary = Messenger::reactionsFor([$message->id], $me);

    expect($summary)->toHaveKey($message->id);

    $thumbs = collect($summary[$message->id])->firstWhere('emoji', '👍');
    $heart = collect($summary[$message->id])->firstWhere('emoji', '❤️');

    expect($thumbs)->toMatchArray(['emoji' => '👍', 'count' => 2, 'reacted' => true])
        ->and($heart)->toMatchArray(['emoji' => '❤️', 'count' => 1, 'reacted' => false]);
});

it('returns an empty summary for messages with no reactions', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $me, 'hello');

    expect(Messenger::reactionsFor([$message->id], $me))->toBe([]);
});

it('dispatches MessageReacted and MessageUnreacted events', function () {
    Illuminate\Support\Facades\Event::fake([MessageReacted::class, MessageUnreacted::class]);

    $me = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $me, 'hello');

    Messenger::react($message, $me, '👍');
    Event::assertDispatched(MessageReacted::class, fn (MessageReacted $e) => $e->reaction->emoji === '👍');

    Messenger::react($message, $me, '👍');
    Event::assertDispatched(MessageUnreacted::class, fn (MessageUnreacted $e) => $e->emoji === '👍' && $e->message->is($message));
});
