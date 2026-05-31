<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Queries\GetInboxConversationsQuery;
use Syriable\Messenger\Tests\Models\User;

/**
 * Send a message at a controllable point in time, so that the resulting
 * conversation's last_message_at is deterministic for ordering assertions.
 */
function inboxSendAt(User $from, User $to, string $body, Carbon $at): void
{
    Carbon::setTestNow($at);
    Messenger::send($from, $to, $body);
}

afterEach(fn () => Carbon::setTestNow());

it('orders the inbox by latest message activity, newest first', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $c = User::factory()->create();

    // Establish three conversations at increasing times.
    inboxSendAt($me, $a, 'to a', now()->copy()->setTime(10, 0));
    inboxSendAt($me, $b, 'to b', now()->copy()->setTime(10, 5));
    inboxSendAt($me, $c, 'to c', now()->copy()->setTime(10, 10));

    // Now bump conversation A to be the most recently active.
    inboxSendAt($a, $me, 'a replies', now()->copy()->setTime(10, 20));

    $inbox = app(GetInboxConversationsQuery::class)->execute($me);

    $order = $inbox->map(fn (Conversation $c) => $c->participantFor($a) ? 'a'
        : ($c->participantFor($b) ? 'b' : 'c'))->all();

    // Most recent activity first: A (10:20), then C (10:10), then B (10:05).
    expect($order)->toBe(['a', 'c', 'b']);
});

it('does not let unread state affect ordering', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();

    inboxSendAt($a, $me, 'older', now()->copy()->setTime(9, 0));
    inboxSendAt($b, $me, 'newer', now()->copy()->setTime(9, 30));

    $convA = Messenger::between($me, $a);
    $convB = Messenger::between($me, $b);

    $baseline = app(GetInboxConversationsQuery::class)->execute($me)
        ->pluck('id')->all();

    expect($baseline)->toBe([$convB->id, $convA->id]);

    // Mutate read/unread state in various ways; ordering must be unchanged.
    Messenger::markAsRead($convB, $me);
    Messenger::markAsUnread($convA, $me);

    $after = app(GetInboxConversationsQuery::class)->execute($me)
        ->pluck('id')->all();

    expect($after)->toBe($baseline);
});

it('excludes archived conversations by default', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();

    Messenger::send($me, $a, 'hi a');
    Messenger::send($me, $b, 'hi b');

    $convA = Messenger::between($me, $a);
    Messenger::archive($convA, $me);

    $inbox = app(GetInboxConversationsQuery::class)->execute($me);

    expect($inbox->pluck('id')->all())->not->toContain($convA->id)
        ->and($inbox)->toHaveCount(1);
});

it('includes archived conversations when include_archived is true', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();

    Messenger::send($me, $a, 'hi a');
    $convA = Messenger::between($me, $a);
    Messenger::archive($convA, $me);

    $inbox = app(GetInboxConversationsQuery::class)->execute($me, ['include_archived' => true]);

    expect($inbox->pluck('id')->all())->toContain($convA->id);
});

it('returns only starred conversations when starred is true', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();

    Messenger::send($me, $a, 'hi a');
    Messenger::send($me, $b, 'hi b');

    $convA = Messenger::between($me, $a);
    Messenger::star($convA, $me);

    $starred = app(GetInboxConversationsQuery::class)->execute($me, ['starred' => true]);

    expect($starred)->toHaveCount(1)
        ->and($starred->first()->id)->toBe($convA->id);
});

it('returns only conversations with unread messages when unread is true', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();

    // Both arrive as inbound messages, so both start unread for $me.
    Messenger::send($a, $me, 'hi from a');
    Messenger::send($b, $me, 'hi from b');

    $convA = Messenger::between($me, $a);
    Messenger::markAsRead($convA, $me);

    $unread = app(GetInboxConversationsQuery::class)->execute($me, ['unread' => true]);

    expect($unread)->toHaveCount(1)
        ->and($unread->first()->id)->toBe(Messenger::between($me, $b)->id);
});

it('does not reorder the inbox when filtering by unread', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();

    inboxSendAt($a, $me, 'older', now()->copy()->setTime(9, 0));
    inboxSendAt($b, $me, 'newer', now()->copy()->setTime(9, 30));

    $unread = app(GetInboxConversationsQuery::class)->execute($me, ['unread' => true]);

    // Both unread; ordering is still latest-activity-first, newest on top.
    expect($unread->pluck('id')->all())->toBe([
        Messenger::between($me, $b)->id,
        Messenger::between($me, $a)->id,
    ]);
});

it('returns only spam conversations when only_spam is true', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();

    Messenger::send($me, $a, 'hi a');
    Messenger::send($me, $b, 'hi b');

    $convA = Messenger::between($me, $a);
    Messenger::spam($convA, $me);

    $spam = app(GetInboxConversationsQuery::class)->execute($me, ['only_spam' => true]);

    expect($spam)->toHaveCount(1)
        ->and($spam->first()->id)->toBe($convA->id);
});

it('only_spam is participant-specific and the inverse of the default inbox', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();

    Messenger::send($me, $a, 'hi a');
    $convA = Messenger::between($me, $a);
    Messenger::spam($convA, $me);

    // Default inbox still shows spam (kept visible per the v1 spec, #82),
    // while only_spam isolates the spam folder.
    $default = app(GetInboxConversationsQuery::class)->execute($me);
    $spam = app(GetInboxConversationsQuery::class)->execute($me, ['only_spam' => true]);

    expect($default->pluck('id')->all())->toContain($convA->id)
        ->and($spam->pluck('id')->all())->toBe([$convA->id])
        // The other participant has not marked it as spam, so their folder is empty.
        ->and(app(GetInboxConversationsQuery::class)->execute($a, ['only_spam' => true]))
        ->toHaveCount(0);
});

it('respects the limit option', function () {
    $me = User::factory()->create();

    foreach (range(1, 4) as $i) {
        $other = User::factory()->create();
        inboxSendAt($me, $other, "msg {$i}", now()->copy()->addMinutes($i));
    }

    $limited = app(GetInboxConversationsQuery::class)->execute($me, ['limit' => 2]);

    expect($limited)->toHaveCount(2);
});

it('hides a cleared conversation when no new message has arrived', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();

    inboxSendAt($a, $me, 'history', now()->copy()->setTime(8, 0));
    $conv = Messenger::between($me, $a);

    Carbon::setTestNow(now()->copy()->setTime(9, 0));
    Messenger::clear($conv, $me);

    $inbox = app(GetInboxConversationsQuery::class)->execute($me);

    expect($inbox->pluck('id')->all())->not->toContain($conv->id)
        ->and($inbox)->toHaveCount(0);
});

it('shows a cleared conversation again once a new message arrives', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();

    inboxSendAt($a, $me, 'history', now()->copy()->setTime(8, 0));
    $conv = Messenger::between($me, $a);

    Carbon::setTestNow(now()->copy()->setTime(9, 0));
    Messenger::clear($conv, $me);

    // A new message arrives strictly after the clear timestamp.
    inboxSendAt($a, $me, 'are you there?', now()->copy()->setTime(10, 0));

    $inbox = app(GetInboxConversationsQuery::class)->execute($me);

    expect($inbox->pluck('id')->all())->toContain($conv->id)
        ->and($inbox)->toHaveCount(1);
});

it('is participant-specific: archiving by one side does not hide it from the other', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'hello');
    $conv = Messenger::between($alice, $bob);

    Messenger::archive($conv, $alice);

    $aliceInbox = app(GetInboxConversationsQuery::class)->execute($alice);
    $bobInbox = app(GetInboxConversationsQuery::class)->execute($bob);

    expect($aliceInbox->pluck('id')->all())->not->toContain($conv->id)
        ->and($bobInbox->pluck('id')->all())->toContain($conv->id);
});

it('is participant-specific: clearing by one side does not hide it from the other', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    inboxSendAt($alice, $bob, 'hello', now()->copy()->setTime(8, 0));
    $conv = Messenger::between($alice, $bob);

    Carbon::setTestNow(now()->copy()->setTime(9, 0));
    Messenger::clear($conv, $alice);

    expect(app(GetInboxConversationsQuery::class)->execute($alice)->pluck('id')->all())
        ->not->toContain($conv->id)
        ->and(app(GetInboxConversationsQuery::class)->execute($bob)->pluck('id')->all())
        ->toContain($conv->id);
});

it('eager-loads participants and lastMessage to avoid N+1', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();

    Messenger::send($me, $a, 'hi a');
    Messenger::send($me, $b, 'hi b');

    $inbox = app(GetInboxConversationsQuery::class)->execute($me);

    expect($inbox)->toHaveCount(2);

    $inbox->each(function (Conversation $conversation) {
        expect($conversation->relationLoaded('participants'))->toBeTrue()
            ->and($conversation->relationLoaded('lastMessage'))->toBeTrue();
    });

    // No further queries are needed to read eager-loaded relations.
    DB::enableQueryLog();
    $inbox->each(fn (Conversation $c) => $c->participants->count());
    $inbox->each(fn (Conversation $c) => $c->lastMessage?->body);
    expect(DB::getQueryLog())->toBeEmpty();
    DB::disableQueryLog();
});
