<?php

use Illuminate\Support\Facades\Event;
use Syriable\Messenger\Actions\StarConversationAction;
use Syriable\Messenger\Events\ConversationStarred;
use Syriable\Messenger\Events\ConversationUnstarred;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Tests\Models\User;

it('stars a conversation for a participant and sets starred_at', function () {
    Event::fake([ConversationStarred::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);

    $row = Messenger::star($conversation, $alice);

    expect($row->isStarred())->toBeTrue()
        ->and($row->starred_at)->not->toBeNull();

    Event::assertDispatched(ConversationStarred::class, fn ($e) => $e->participant->is($row));
});

it('keeps starring participant-specific', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);
    Messenger::star($conversation, $alice);

    $fresh = Messenger::between($alice, $bob);

    expect($fresh->participantFor($alice)->isStarred())->toBeTrue()
        ->and($fresh->participantFor($bob)->isStarred())->toBeFalse();
});

it('unstars a conversation and clears starred_at', function () {
    Event::fake([ConversationUnstarred::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);
    Messenger::star($conversation, $alice);

    $row = Messenger::unstar($conversation, $alice);

    expect($row->isStarred())->toBeFalse()
        ->and($row->starred_at)->toBeNull();

    Event::assertDispatched(ConversationUnstarred::class, fn ($e) => $e->participant->is($row));
});

it('throws InvalidParticipantException when starring for a non-member', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $stranger = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);

    expect(fn () => app(StarConversationAction::class)->execute($conversation, $stranger))
        ->toThrow(InvalidParticipantException::class);
});
