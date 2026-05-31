<?php

use Illuminate\Support\Carbon;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Queries\GetConversationMessagesQuery;
use Syriable\Messenger\Tests\Models\User;

afterEach(fn () => Carbon::setTestNow());

/**
 * Build a conversation of N chronologically-ordered messages ("msg 1".."msg N")
 * and return [conversation, messages-keyed-by-body].
 */
function buildTimeline(int $count): array
{
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $byBody = [];
    foreach (range(1, $count) as $i) {
        Carbon::setTestNow(now()->copy()->setTime(8, 0)->addSeconds($i));
        $message = Messenger::send($i % 2 ? $alice : $bob, $i % 2 ? $bob : $alice, "msg {$i}");
        $byBody["msg {$i}"] = $message;
    }

    return [Messenger::between($alice, $bob), $alice, $byBody];
}

it('loads the previous N messages before a cursor in chronological order', function () {
    [$conv, $alice, $messages] = buildTimeline(10);

    $page = app(GetConversationMessagesQuery::class)->execute($conv, $alice, [
        'before_id' => $messages['msg 8']->getKey(),
        'limit' => 3,
    ]);

    // The three immediately before msg 8, ascending and excluding the cursor.
    expect($page->pluck('body')->all())->toBe(['msg 5', 'msg 6', 'msg 7']);
});

it('loads the next N messages after a cursor in chronological order', function () {
    [$conv, $alice, $messages] = buildTimeline(10);

    $page = app(GetConversationMessagesQuery::class)->execute($conv, $alice, [
        'after_id' => $messages['msg 3']->getKey(),
        'limit' => 2,
    ]);

    expect($page->pluck('body')->all())->toBe(['msg 4', 'msg 5']);
});

it('returns every message before a cursor when no limit is given', function () {
    [$conv, $alice, $messages] = buildTimeline(6);

    $page = app(GetConversationMessagesQuery::class)->execute($conv, $alice, [
        'before_id' => $messages['msg 4']->getKey(),
    ]);

    expect($page->pluck('body')->all())->toBe(['msg 1', 'msg 2', 'msg 3']);
});

it('returns every message after a cursor when no limit is given', function () {
    [$conv, $alice, $messages] = buildTimeline(6);

    $page = app(GetConversationMessagesQuery::class)->execute($conv, $alice, [
        'after_id' => $messages['msg 3']->getKey(),
    ]);

    expect($page->pluck('body')->all())->toBe(['msg 4', 'msg 5', 'msg 6']);
});

it('fans out the full unbounded page across a large thread when no limit is given', function () {
    // Documents the contract: a cursor without a limit returns the *entire*
    // matching range. Callers paginating a large thread must pass a limit to
    // bound the page; this guards against a future change silently capping it.
    [$conv, $alice, $messages] = buildTimeline(50);

    $before = app(GetConversationMessagesQuery::class)->execute($conv, $alice, [
        'before_id' => $messages['msg 50']->getKey(),
    ]);
    $after = app(GetConversationMessagesQuery::class)->execute($conv, $alice, [
        'after_id' => $messages['msg 1']->getKey(),
    ]);

    // 49 messages on either side of the cursor, both in chronological order.
    expect($before)->toHaveCount(49)
        ->and($before->first()->body)->toBe('msg 1')
        ->and($before->last()->body)->toBe('msg 49')
        ->and($after)->toHaveCount(49)
        ->and($after->first()->body)->toBe('msg 2')
        ->and($after->last()->body)->toBe('msg 50');
});

it('clamps a non-positive limit to a single result on a cursor page instead of returning everything', function () {
    [$conv, $alice, $messages] = buildTimeline(10);

    // A zero/negative limit must never disable the LIMIT clause and leak the
    // whole unbounded range — it clamps to one, just like the non-cursor path.
    $before = app(GetConversationMessagesQuery::class)->execute($conv, $alice, [
        'before_id' => $messages['msg 8']->getKey(),
        'limit' => 0,
    ]);
    $after = app(GetConversationMessagesQuery::class)->execute($conv, $alice, [
        'after_id' => $messages['msg 3']->getKey(),
        'limit' => -5,
    ]);

    expect($before->pluck('body')->all())->toBe(['msg 7'])
        ->and($after->pluck('body')->all())->toBe(['msg 4']);
});

it('keysets on (created_at, id) so same-timestamp messages paginate deterministically', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // Three messages sharing the exact same microsecond timestamp.
    Carbon::setTestNow(now()->copy()->setTime(8, 0, 0, 123456));
    $a = Messenger::send($alice, $bob, 'a');
    $b = Messenger::send($alice, $bob, 'b');
    $c = Messenger::send($alice, $bob, 'c');

    $conv = Messenger::between($alice, $bob);

    // ULIDs are monotonic within the same ms, so id order matches send order.
    $ordered = collect([$a, $b, $c])->sortBy('id')->values();
    $middle = $ordered[1];

    $before = app(GetConversationMessagesQuery::class)->execute($conv, $alice, [
        'before_id' => $middle->getKey(),
    ]);
    $after = app(GetConversationMessagesQuery::class)->execute($conv, $alice, [
        'after_id' => $middle->getKey(),
    ]);

    expect($before->pluck('id')->all())->toBe([$ordered[0]->id])
        ->and($after->pluck('id')->all())->toBe([$ordered[2]->id]);
});

it('respects the clear boundary together with a cursor', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    foreach (range(1, 4) as $i) {
        Carbon::setTestNow(now()->copy()->setTime(8, 0)->addSeconds($i));
        Messenger::send($alice, $bob, "pre {$i}");
    }

    $conv = Messenger::between($alice, $bob);
    Carbon::setTestNow(now()->copy()->setTime(9, 0));
    Messenger::clear($conv, $alice);

    $post = [];
    foreach (range(1, 4) as $i) {
        Carbon::setTestNow(now()->copy()->setTime(10, 0)->addSeconds($i));
        $post[$i] = Messenger::send($bob, $alice, "post {$i}");
    }

    // Walking back from the newest post-clear message must never surface
    // pre-clear history, even without an explicit limit.
    $page = app(GetConversationMessagesQuery::class)->execute($conv, $alice, [
        'before_id' => $post[4]->getKey(),
    ]);

    expect($page->pluck('body')->all())->toBe(['post 1', 'post 2', 'post 3']);
});

it('rejects a cursor message from another conversation', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $carol = User::factory()->create();

    Messenger::send($alice, $bob, 'in ab');
    $foreign = Messenger::send($alice, $carol, 'in ac');

    $conv = Messenger::between($alice, $bob);

    app(GetConversationMessagesQuery::class)->execute($conv, $alice, [
        'before_id' => $foreign->getKey(),
    ]);
})->throws(InvalidArgumentException::class);

it('rejects supplying both before_id and after_id', function () {
    [$conv, $alice, $messages] = buildTimeline(3);

    app(GetConversationMessagesQuery::class)->execute($conv, $alice, [
        'before_id' => $messages['msg 1']->getKey(),
        'after_id' => $messages['msg 2']->getKey(),
    ]);
})->throws(InvalidArgumentException::class);

it('eager-loads relations on cursor pages', function () {
    [$conv, $alice, $messages] = buildTimeline(5);

    $page = app(GetConversationMessagesQuery::class)->execute($conv, $alice, [
        'before_id' => $messages['msg 5']->getKey(),
        'limit' => 2,
    ]);

    $page->each(function (Message $message) {
        expect($message->relationLoaded('attachments'))->toBeTrue()
            ->and($message->relationLoaded('replyTo'))->toBeTrue();
    });
});
