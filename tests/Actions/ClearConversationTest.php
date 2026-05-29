<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Syriable\Messenger\Actions\ClearConversationAction;
use Syriable\Messenger\Events\ConversationCleared;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Tests\Models\User;

afterEach(fn () => Carbon::setTestNow());

function sendAt(User $from, User $to, string $body, Carbon $at): Message
{
    Carbon::setTestNow($at);

    return Messenger::send($from, $to, $body);
}

it('does not delete messages and sets cleared_at while resetting unread_count', function () {
    Event::fake([ConversationCleared::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    sendAt($alice, $bob, 'one', now()->copy()->setTime(8, 0));
    sendAt($bob, $alice, 'two', now()->copy()->setTime(8, 1));

    $conversation = Messenger::between($alice, $bob);

    Carbon::setTestNow(now()->copy()->setTime(9, 0));
    $row = Messenger::clear($conversation, $alice);

    expect($row->cleared_at)->not->toBeNull()
        ->and($row->unread_count)->toBe(0)
        // Messages remain in the database untouched.
        ->and(Message::count())->toBe(2);

    Event::assertDispatched(ConversationCleared::class, fn ($e) => $e->participant->is($row));
});

it('hides previously visible messages from the clearer only', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    sendAt($alice, $bob, 'one', now()->copy()->setTime(8, 0));
    sendAt($bob, $alice, 'two', now()->copy()->setTime(8, 1));

    $conversation = Messenger::between($alice, $bob);

    Carbon::setTestNow(now()->copy()->setTime(9, 0));
    Messenger::clear($conversation, $alice);

    // The clearer sees nothing prior to the clear timestamp.
    expect(Messenger::messages($conversation, $alice))->toHaveCount(0);
    // The other participant is unaffected.
    expect(Messenger::messages($conversation, $bob))->toHaveCount(2);
});

it('makes the conversation visible again to the clearer for messages after cleared_at', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    sendAt($alice, $bob, 'one', now()->copy()->setTime(8, 0));
    sendAt($bob, $alice, 'two', now()->copy()->setTime(8, 1));

    $conversation = Messenger::between($alice, $bob);

    Carbon::setTestNow(now()->copy()->setTime(9, 0));
    Messenger::clear($conversation, $alice);

    // A new incoming message arrives after the clear.
    sendAt($bob, $alice, 'three', now()->copy()->setTime(10, 0));

    $visible = Messenger::messages($conversation, $alice);

    expect($visible)->toHaveCount(1)
        ->and($visible->first()->body)->toBe('three');
});

it('throws InvalidParticipantException when clearing for a non-member', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $stranger = User::factory()->create();
    Messenger::send($alice, $bob, 'Hello');

    $conversation = Messenger::between($alice, $bob);

    expect(fn () => app(ClearConversationAction::class)->execute($conversation, $stranger))
        ->toThrow(InvalidParticipantException::class);
});
