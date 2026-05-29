<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Queries\GetConversationMessagesQuery;
use Syriable\Messenger\Tests\Models\User;

afterEach(fn () => Carbon::setTestNow());

function messageAt(User $from, User $to, string $body, Carbon $at): Message
{
    Carbon::setTestNow($at);

    return Messenger::send($from, $to, $body);
}

it('returns messages chronologically with the newest at the bottom', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    messageAt($alice, $bob, 'first', now()->copy()->setTime(8, 0));
    messageAt($bob, $alice, 'second', now()->copy()->setTime(8, 1));
    messageAt($alice, $bob, 'third', now()->copy()->setTime(8, 2));

    $conv = Messenger::between($alice, $bob);

    $messages = app(GetConversationMessagesQuery::class)->execute($conv, $alice);

    expect($messages->pluck('body')->all())->toBe(['first', 'second', 'third']);
});

it('hides messages at or before the participant clear timestamp', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    messageAt($alice, $bob, 'old one', now()->copy()->setTime(8, 0));
    messageAt($bob, $alice, 'old two', now()->copy()->setTime(8, 1));

    $conv = Messenger::between($alice, $bob);

    Carbon::setTestNow(now()->copy()->setTime(9, 0));
    Messenger::clear($conv, $alice);

    messageAt($bob, $alice, 'after clear', now()->copy()->setTime(10, 0));

    $aliceMessages = app(GetConversationMessagesQuery::class)->execute($conv, $alice);

    expect($aliceMessages->pluck('body')->all())->toBe(['after clear']);
});

it('still shows all messages to the other participant after one side clears', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    messageAt($alice, $bob, 'old one', now()->copy()->setTime(8, 0));
    messageAt($bob, $alice, 'old two', now()->copy()->setTime(8, 1));

    $conv = Messenger::between($alice, $bob);

    Carbon::setTestNow(now()->copy()->setTime(9, 0));
    Messenger::clear($conv, $alice);

    messageAt($bob, $alice, 'after clear', now()->copy()->setTime(10, 0));

    $bobMessages = app(GetConversationMessagesQuery::class)->execute($conv, $bob);

    expect($bobMessages->pluck('body')->all())->toBe(['old one', 'old two', 'after clear']);
});

it('limits to the latest N visible messages but keeps chronological order', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    foreach (range(1, 5) as $i) {
        messageAt($i % 2 ? $alice : $bob, $i % 2 ? $bob : $alice, "msg {$i}", now()->copy()->addMinutes($i));
    }

    $conv = Messenger::between($alice, $bob);

    $messages = app(GetConversationMessagesQuery::class)->execute($conv, $alice, ['limit' => 2]);

    // Latest 2 messages (msg 4, msg 5) but in ascending order.
    expect($messages->pluck('body')->all())->toBe(['msg 4', 'msg 5']);
});

it('applies the clear filter together with the limit option', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    messageAt($alice, $bob, 'pre 1', now()->copy()->setTime(8, 0));
    messageAt($bob, $alice, 'pre 2', now()->copy()->setTime(8, 1));

    $conv = Messenger::between($alice, $bob);

    Carbon::setTestNow(now()->copy()->setTime(9, 0));
    Messenger::clear($conv, $alice);

    messageAt($bob, $alice, 'post 1', now()->copy()->setTime(10, 0));
    messageAt($alice, $bob, 'post 2', now()->copy()->setTime(10, 1));
    messageAt($bob, $alice, 'post 3', now()->copy()->setTime(10, 2));

    $messages = app(GetConversationMessagesQuery::class)->execute($conv, $alice, ['limit' => 2]);

    // Only post-clear messages are visible; latest two, chronological.
    expect($messages->pluck('body')->all())->toBe(['post 2', 'post 3']);
});

it('throws when the participant is not a member of the conversation', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $stranger = User::factory()->create();

    Messenger::send($alice, $bob, 'hi');
    $conv = Messenger::between($alice, $bob);

    app(GetConversationMessagesQuery::class)->execute($conv, $stranger);
})->throws(InvalidParticipantException::class);

it('eager-loads attachments and replyTo to avoid N+1', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    messageAt($alice, $bob, 'parent', now()->copy()->setTime(8, 0));
    messageAt($bob, $alice, 'child', now()->copy()->setTime(8, 1));

    $conv = Messenger::between($alice, $bob);

    $messages = app(GetConversationMessagesQuery::class)->execute($conv, $alice);

    $messages->each(function (Message $message) {
        expect($message->relationLoaded('attachments'))->toBeTrue()
            ->and($message->relationLoaded('replyTo'))->toBeTrue();
    });

    DB::enableQueryLog();
    $messages->each(fn (Message $m) => $m->attachments->count());
    $messages->each(fn (Message $m) => $m->replyTo?->body);
    expect(DB::getQueryLog())->toBeEmpty();
    DB::disableQueryLog();
});

it('eager-loads relations when using the limit branch too', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    messageAt($alice, $bob, 'one', now()->copy()->setTime(8, 0));
    messageAt($bob, $alice, 'two', now()->copy()->setTime(8, 1));

    $conv = Messenger::between($alice, $bob);

    $messages = app(GetConversationMessagesQuery::class)->execute($conv, $alice, ['limit' => 1]);

    $messages->each(function (Message $message) {
        expect($message->relationLoaded('attachments'))->toBeTrue()
            ->and($message->relationLoaded('replyTo'))->toBeTrue();
    });
});
