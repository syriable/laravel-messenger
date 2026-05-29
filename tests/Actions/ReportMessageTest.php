<?php

use Illuminate\Support\Facades\Event;
use Syriable\Messenger\Actions\ReportMessageAction;
use Syriable\Messenger\Events\MessageReported;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\MessageReport;
use Syriable\Messenger\Tests\Models\User;

it('creates a message report for a (message, reporter) pair', function () {
    Event::fake([MessageReported::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $message = Messenger::send($alice, $bob, 'spammy content');

    $report = Messenger::report($message, $bob, 'spam', 'looks like spam');

    expect($report)->toBeInstanceOf(MessageReport::class)
        ->and($report->message_id)->toBe($message->getKey())
        ->and($report->reporter_type)->toBe($bob->getMorphClass())
        ->and((string) $report->reporter_id)->toBe((string) $bob->getKey())
        ->and($report->reason)->toBe('spam')
        ->and($report->note)->toBe('looks like spam')
        ->and(MessageReport::count())->toBe(1);

    Event::assertDispatched(MessageReported::class, fn ($e) => $e->report->is($report));
});

it('allows reason and note to be optional (null)', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $message = Messenger::send($alice, $bob, 'hello');

    $report = Messenger::report($message, $bob);

    expect($report->reason)->toBeNull()
        ->and($report->note)->toBeNull();
});

it('is message-based, not conversation-based: each message gets its own report', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $first = Messenger::send($alice, $bob, 'one');
    $second = Messenger::send($alice, $bob, 'two');

    Messenger::report($first, $bob, 'spam');
    Messenger::report($second, $bob, 'abuse');

    expect(MessageReport::count())->toBe(2);
});

it('does not create a duplicate when the same reporter reports the same message twice', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $message = Messenger::send($alice, $bob, 'hello');

    $first = Messenger::report($message, $bob, 'spam');
    $second = Messenger::report($message, $bob, 'spam again');

    expect(MessageReport::count())->toBe(1)
        // Same underlying record was updated, not duplicated.
        ->and($second->getKey())->toBe($first->getKey());
});

it('updates the reason and note on a repeated report of the same message', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $message = Messenger::send($alice, $bob, 'hello');

    Messenger::report($message, $bob, 'spam', 'first note');
    $updated = Messenger::report($message, $bob, 'harassment', 'second note');

    expect(MessageReport::count())->toBe(1)
        ->and($updated->reason)->toBe('harassment')
        ->and($updated->note)->toBe('second note');
});

it('keeps reports from different reporters of the same message distinct', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $carol = User::factory()->create();
    $message = Messenger::send($alice, $bob, 'hello');

    Messenger::report($message, $bob, 'spam');
    Messenger::report($message, $carol, 'spam');

    expect(MessageReport::count())->toBe(2);
});

it('dispatches MessageReported once per report call', function () {
    Event::fake([MessageReported::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $message = Messenger::send($alice, $bob, 'hello');

    Messenger::report($message, $bob, 'spam');
    Messenger::report($message, $bob, 'spam again');

    Event::assertDispatchedTimes(MessageReported::class, 2);
});

it('resolves the same report through the action class directly', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $message = Messenger::send($alice, $bob, 'hello');

    $report = app(ReportMessageAction::class)->execute($message, $bob, 'spam');

    expect($report)->toBeInstanceOf(MessageReport::class)
        ->and($report->reason)->toBe('spam');
});
