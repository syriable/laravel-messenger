<?php

use Illuminate\Support\Carbon;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Tests\Models\User;

afterEach(fn () => Carbon::setTestNow());

// Issue #63 — a message sent in the same wall-clock second as a clear (but a
// few microseconds later, as happens in production) must still be visible to
// the clearer and resurface the conversation. Microsecond-precision columns
// keep the boundary correct where second resolution wrongly tied them.
it('shows a message sent microseconds after the clear in the same second', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Carbon::setTestNow('2026-05-30 12:00:00.100000');
    Messenger::send($alice, $bob, 'before');
    $conversation = Messenger::between($alice, $bob);

    // Clear, then a reply lands a few microseconds later — same wall-clock second.
    Carbon::setTestNow('2026-05-30 12:00:00.500000');
    Messenger::clear($conversation, $alice);

    Carbon::setTestNow('2026-05-30 12:00:00.900000');
    Messenger::send($bob, $alice, 'same second');

    $visible = Messenger::messages($conversation, $alice);

    expect($visible)->toHaveCount(1)
        ->and($visible->first()->body)->toBe('same second');
});

it('resurfaces a cleared conversation in the inbox on a same-second reply', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Carbon::setTestNow('2026-05-30 12:00:00.100000');
    Messenger::send($alice, $bob, 'before');
    $conversation = Messenger::between($alice, $bob);

    Carbon::setTestNow('2026-05-30 12:00:00.500000');
    Messenger::clear($conversation, $alice);
    expect(Messenger::inbox($alice))->toHaveCount(0);

    Carbon::setTestNow('2026-05-30 12:00:00.900000');
    Messenger::send($bob, $alice, 'same second');

    expect(Messenger::inbox($alice))->toHaveCount(1);
});

it('still hides messages that predate the clear within the same second', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Carbon::setTestNow('2026-05-30 12:00:00.100000');
    Messenger::send($alice, $bob, 'old one');
    Carbon::setTestNow('2026-05-30 12:00:00.200000');
    Messenger::send($bob, $alice, 'old two');
    $conversation = Messenger::between($alice, $bob);

    Carbon::setTestNow('2026-05-30 12:00:00.500000');
    Messenger::clear($conversation, $alice);

    Carbon::setTestNow('2026-05-30 12:00:00.900000');
    Messenger::send($bob, $alice, 'new');

    expect(Messenger::messages($conversation, $alice))->toHaveCount(1)
        ->and(Messenger::messages($conversation, $alice)->first()->body)->toBe('new')
        // The other participant still sees everything.
        ->and(Messenger::messages($conversation, $bob))->toHaveCount(3);
});

it('persists message timestamps with microsecond precision', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Carbon::setTestNow('2026-05-30 12:00:00.123456');
    $message = Messenger::send($alice, $bob, 'hi');

    expect($message->fresh()->created_at->format('u'))->toBe('123456');
});
