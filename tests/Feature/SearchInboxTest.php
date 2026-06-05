<?php

use Illuminate\Support\Carbon;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Tests\Models\User;
use Syriable\Messenger\Tests\Support\UserNameSearchResolver;

/**
 * Inbox search (#F1.4): finds conversations by message body, or by the other
 * participant's name when a ParticipantSearchResolver is bound. Honours the
 * clear boundary and archived exclusion.
 */
afterEach(fn () => Carbon::setTestNow());

it('returns nothing for a blank term', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    Messenger::send($alice, $me, 'hello');

    expect(Messenger::searchInbox($me, '   '))->toHaveCount(0);
});

it('finds conversations by message body, case-insensitively', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    Messenger::send($alice, $me, 'Project deadline is Friday');
    Messenger::send($bob, $me, 'lunch tomorrow?');

    $results = Messenger::searchInbox($me, 'DEADLINE');

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe(Messenger::between($me, $alice)->id);
});

it('matches a body sent by either participant', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();

    Messenger::send($me, $alice, 'I will send the invoice');

    expect(Messenger::searchInbox($me, 'invoice'))->toHaveCount(1);
});

it('does not match participant name without a resolver', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($alice, $me, 'hello');

    // Default NullParticipantSearchResolver → name search disabled.
    expect(Messenger::searchInbox($me, 'Alice'))->toHaveCount(0);
});

it('finds conversations by participant name when a resolver is bound', function () {
    config()->set('messenger.ui.search_resolver', UserNameSearchResolver::class);

    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    Messenger::send($alice, $me, 'x');
    Messenger::send($bob, $me, 'y');

    $results = Messenger::searchInbox($me, 'alice');

    expect($results->pluck('id')->all())->toBe([Messenger::between($me, $alice)->id]);
});

it('does not match the viewer own name via the resolver', function () {
    config()->set('messenger.ui.search_resolver', UserNameSearchResolver::class);

    $me = User::factory()->create(['name' => 'Alice']);
    $other = User::factory()->create(['name' => 'Bob']);
    Messenger::send($other, $me, 'hi');

    // "Alice" matches the viewer, but the viewer is excluded — body doesn't match
    // either, so no result.
    expect(Messenger::searchInbox($me, 'Alice'))->toHaveCount(0);
});

it('orders results by latest activity and respects the limit', function () {
    $me = User::factory()->create();

    foreach (range(1, 3) as $i) {
        $other = User::factory()->create();
        Carbon::setTestNow(now()->copy()->addMinutes($i));
        Messenger::send($other, $me, "shared keyword {$i}");
    }
    Carbon::setTestNow();

    $results = Messenger::searchInbox($me, 'keyword', ['limit' => 2]);

    expect($results)->toHaveCount(2);
});

it('excludes archived conversations by default and includes them on request', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    Messenger::send($alice, $me, 'archived keyword');
    $conversation = Messenger::between($me, $alice);
    Messenger::archive($conversation, $me);

    expect(Messenger::searchInbox($me, 'keyword'))->toHaveCount(0)
        ->and(Messenger::searchInbox($me, 'keyword', ['include_archived' => true]))->toHaveCount(1);
});

it('hides a cleared conversation until a newer message arrives', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();

    Carbon::setTestNow(now()->copy()->setTime(8, 0));
    Messenger::send($alice, $me, 'old keyword');
    $conversation = Messenger::between($me, $alice);

    Carbon::setTestNow(now()->copy()->setTime(9, 0));
    Messenger::clear($conversation, $me);

    expect(Messenger::searchInbox($me, 'keyword'))->toHaveCount(0);
});
