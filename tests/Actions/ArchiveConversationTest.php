<?php

use Illuminate\Support\Facades\Event;
use Syriable\Messenger\Actions\ArchiveConversationAction;
use Syriable\Messenger\Events\ConversationArchived;
use Syriable\Messenger\Events\ConversationUnarchived;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Tests\Models\User;

it('archives a conversation for a single participant and sets archived_at', function () {
    Event::fake([ConversationArchived::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);

    $row = Messenger::archive($conversation, $alice);

    expect($row->isArchived())->toBeTrue()
        ->and($row->archived_at)->not->toBeNull();

    Event::assertDispatched(ConversationArchived::class, fn ($e) => $e->participant->is($row));
});

it('keeps archiving participant-specific (archiving for A does not affect B)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);

    Messenger::archive($conversation, $alice);

    $fresh = Messenger::between($alice, $bob);

    expect($fresh->participantFor($alice)->isArchived())->toBeTrue()
        ->and($fresh->participantFor($bob)->isArchived())->toBeFalse();
});

it('unarchives a conversation and clears archived_at', function () {
    Event::fake([ConversationUnarchived::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);
    Messenger::archive($conversation, $alice);

    $row = Messenger::unarchive($conversation, $alice);

    expect($row->isArchived())->toBeFalse()
        ->and($row->archived_at)->toBeNull();

    Event::assertDispatched(ConversationUnarchived::class, fn ($e) => $e->participant->is($row));
});

it('unarchives the conversation for both participants when a new message is sent', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);
    Messenger::archive($conversation, $alice);
    Messenger::archive($conversation, $bob);

    // A new message resurfaces the conversation for both sides.
    Messenger::send($bob, $alice, 'You there?');

    $fresh = Messenger::between($alice, $bob);

    expect($fresh->participantFor($alice)->archived_at)->toBeNull()
        ->and($fresh->participantFor($bob)->archived_at)->toBeNull();
});

it('throws InvalidParticipantException when archiving for a non-member', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $stranger = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);

    expect(fn () => app(ArchiveConversationAction::class)->execute($conversation, $stranger))
        ->toThrow(InvalidParticipantException::class);
});
