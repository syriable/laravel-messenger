<?php

use Illuminate\Support\Facades\Event;
use Syriable\Messenger\Actions\MarkConversationAsReadAction;
use Syriable\Messenger\Actions\MarkConversationAsUnreadAction;
use Syriable\Messenger\Events\ConversationMarkedAsUnread;
use Syriable\Messenger\Events\ConversationRead;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Tests\Models\User;

it('marks a conversation as read: sets last_read_at and zeroes unread_count', function () {
    Event::fake([ConversationRead::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'one');
    Messenger::send($alice, $bob, 'two');

    $conversation = Messenger::between($alice, $bob);

    // Bob has received two unread messages so far.
    expect($conversation->participantFor($bob)->unread_count)->toBe(2);

    $row = Messenger::markAsRead($conversation, $bob);

    expect($row->unread_count)->toBe(0)
        ->and($row->last_read_at)->not->toBeNull()
        ->and($row->isUnread())->toBeFalse();

    Event::assertDispatched(ConversationRead::class, fn ($e) => $e->participant->is($row));
});

it('marks a conversation as read for the calling participant only', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'hello');

    $conversation = Messenger::between($alice, $bob);
    Messenger::markAsRead($conversation, $bob);

    $fresh = Messenger::between($alice, $bob);

    expect($fresh->participantFor($bob)->unread_count)->toBe(0)
        ->and($fresh->participantFor($bob)->last_read_at)->not->toBeNull();
});

it('marks a conversation as unread: unread_count becomes 1 and last_read_at is cleared', function () {
    Event::fake([ConversationMarkedAsUnread::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'one');
    Messenger::send($alice, $bob, 'two');

    $conversation = Messenger::between($alice, $bob);
    // Bob first reads everything.
    Messenger::markAsRead($conversation, $bob);

    $row = Messenger::markAsUnread($conversation, $bob);

    // Only the last received message is treated as unread.
    expect($row->unread_count)->toBe(1)
        ->and($row->last_read_at)->toBeNull()
        ->and($row->isUnread())->toBeTrue();

    Event::assertDispatched(ConversationMarkedAsUnread::class, fn ($e) => $e->participant->is($row));
});

it('does not reorder the inbox when marking as unread (last_message_at unchanged)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'one');
    Messenger::send($alice, $bob, 'two');

    $conversation = Messenger::between($alice, $bob);
    $before = $conversation->last_message_at;

    Messenger::markAsRead($conversation, $bob);
    Messenger::markAsUnread($conversation, $bob);

    $after = Messenger::between($alice, $bob)->last_message_at;

    expect($after->equalTo($before))->toBeTrue();
});

it('throws InvalidParticipantException when marking as read for a non-member', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $stranger = User::factory()->create();
    Messenger::send($alice, $bob, 'hello');

    $conversation = Messenger::between($alice, $bob);

    expect(fn () => app(MarkConversationAsReadAction::class)->execute($conversation, $stranger))
        ->toThrow(InvalidParticipantException::class);
});

it('throws InvalidParticipantException when marking as unread for a non-member', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $stranger = User::factory()->create();
    Messenger::send($alice, $bob, 'hello');

    $conversation = Messenger::between($alice, $bob);

    expect(fn () => app(MarkConversationAsUnreadAction::class)->execute($conversation, $stranger))
        ->toThrow(InvalidParticipantException::class);
});

it('increments the recipient unread_count per received message while sender stays zero', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'one');
    Messenger::send($alice, $bob, 'two');
    Messenger::send($alice, $bob, 'three');

    $conversation = Messenger::between($alice, $bob);

    expect($conversation->participantFor($bob)->unread_count)->toBe(3)
        ->and($conversation->participantFor($alice)->unread_count)->toBe(0);
});

it('resets the sender unread_count to zero when they reply', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // Alice sends two; Bob now has 2 unread.
    Messenger::send($alice, $bob, 'one');
    Messenger::send($alice, $bob, 'two');

    // Bob replies, implicitly reading the conversation.
    Messenger::send($bob, $alice, 'reply');

    $conversation = Messenger::between($alice, $bob);

    expect($conversation->participantFor($bob)->unread_count)->toBe(0)
        // Alice now has the single reply as unread.
        ->and($conversation->participantFor($alice)->unread_count)->toBe(1);
});
