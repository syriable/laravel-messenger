<?php

use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Queries\FindConversationBetweenQuery;
use Syriable\Messenger\Tests\Models\User;

it('returns null when no conversation exists between two participants', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    expect(app(FindConversationBetweenQuery::class)->execute($alice, $bob))->toBeNull();
});

it('finds the conversation between two participants', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'hi');

    $conv = app(FindConversationBetweenQuery::class)->execute($alice, $bob);

    expect($conv)->toBeInstanceOf(Conversation::class)
        ->and($conv->participantFor($alice))->not->toBeNull()
        ->and($conv->participantFor($bob))->not->toBeNull();
});

it('is direction-independent', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'hi');

    $forward = app(FindConversationBetweenQuery::class)->execute($alice, $bob);
    $reverse = app(FindConversationBetweenQuery::class)->execute($bob, $alice);

    expect($reverse->id)->toBe($forward->id);
});

it('does not match conversations with a different participant', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $carol = User::factory()->create();

    Messenger::send($alice, $bob, 'hi');

    expect(app(FindConversationBetweenQuery::class)->execute($alice, $carol))->toBeNull();
});

it('eager-loads participants by default', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'hi');

    $conv = app(FindConversationBetweenQuery::class)->execute($alice, $bob);

    expect($conv->relationLoaded('participants'))->toBeTrue();
});

it('can skip eager-loading participants when requested', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'hi');

    $conv = app(FindConversationBetweenQuery::class)->execute($alice, $bob, false);

    expect($conv->relationLoaded('participants'))->toBeFalse();
});

it('is read-only and does not create or mutate state', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    expect(Conversation::count())->toBe(0);

    app(FindConversationBetweenQuery::class)->execute($alice, $bob);

    expect(Conversation::count())->toBe(0);
});

it('is exposed through the facade between() method', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'hi');

    expect(Messenger::between($alice, $bob))->toBeInstanceOf(Conversation::class)
        ->and(Messenger::between($bob, $alice))->not->toBeNull();
});
