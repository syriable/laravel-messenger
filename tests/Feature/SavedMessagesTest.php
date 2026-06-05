<?php

use Illuminate\Support\Carbon;
use Syriable\Messenger\Events\MessageSaved;
use Syriable\Messenger\Events\MessageUnsaved;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Support\Models;
use Syriable\Messenger\Tests\Models\User;

/**
 * Saved messages (#F1.3): a per-participant, additive bookmark. It never affects
 * the conversation or message, and is idempotent.
 */
afterEach(fn () => Carbon::setTestNow());

it('saves a message for a participant and returns the saved row', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $me, 'save me');

    $saved = Messenger::save($message, $me);

    expect($saved->message_id)->toBe($message->id)
        ->and($saved->conversation_id)->toBe($message->conversation_id)
        ->and($saved->participant_type)->toBe($me->getMorphClass())
        ->and((string) $saved->participant_id)->toBe((string) $me->getKey())
        ->and(Messenger::isSaved($message, $me))->toBeTrue();
});

it('is idempotent: saving twice keeps a single row', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $me, 'save me');

    Messenger::save($message, $me);
    Messenger::save($message, $me);

    expect(Models::savedMessage()::query()->where('message_id', $message->id)->count())->toBe(1);
});

it('unsaves a message', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $me, 'save me');

    Messenger::save($message, $me);
    Messenger::unsave($message, $me);

    expect(Messenger::isSaved($message, $me))->toBeFalse()
        ->and(Models::savedMessage()::query()->count())->toBe(0);
});

it('is participant-specific', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $me, 'save me');

    Messenger::save($message, $me);

    expect(Messenger::isSaved($message, $me))->toBeTrue()
        ->and(Messenger::isSaved($message, $alice))->toBeFalse();
});

it('lists saved messages most recently saved first', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();

    $a = Messenger::send($alice, $me, 'first');
    $b = Messenger::send($alice, $me, 'second');

    Carbon::setTestNow(now()->subMinute());
    Messenger::save($a, $me);
    Carbon::setTestNow(now()->addMinute());
    Messenger::save($b, $me);
    Carbon::setTestNow();

    $saved = Messenger::saved($me);

    expect($saved->pluck('id')->all())->toBe([$b->id, $a->id]);
});

it('scopes saved messages to a conversation', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $withAlice = Messenger::send($alice, $me, 'from alice');
    $withBob = Messenger::send($bob, $me, 'from bob');

    Messenger::save($withAlice, $me);
    Messenger::save($withBob, $me);

    $aliceConversation = Messenger::between($me, $alice);
    $scoped = Messenger::saved($me, ['conversation_id' => $aliceConversation->id]);

    expect($scoped->pluck('id')->all())->toBe([$withAlice->id]);
});

it('eager-loads relations and respects the limit', function () {
    $me = User::factory()->create();
    $alice = User::factory()->create();

    foreach (range(1, 3) as $i) {
        Messenger::save(Messenger::send($alice, $me, "m{$i}"), $me);
    }

    $saved = Messenger::saved($me, ['limit' => 2]);

    expect($saved)->toHaveCount(2)
        ->and($saved->first()->relationLoaded('attachments'))->toBeTrue();
});

it('dispatches MessageSaved and MessageUnsaved events', function () {
    Illuminate\Support\Facades\Event::fake([MessageSaved::class, MessageUnsaved::class]);

    $me = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $me, 'save me');

    Messenger::save($message, $me);
    Event::assertDispatched(MessageSaved::class, fn (MessageSaved $e) => $e->savedMessage->message_id === $message->id);

    Messenger::unsave($message, $me);
    Event::assertDispatched(MessageUnsaved::class, fn (MessageUnsaved $e) => $e->message->is($message));
});

it('does not dispatch MessageUnsaved when nothing was saved', function () {
    Illuminate\Support\Facades\Event::fake([MessageUnsaved::class]);

    $me = User::factory()->create();
    $alice = User::factory()->create();
    $message = Messenger::send($alice, $me, 'never saved');

    Messenger::unsave($message, $me);

    Event::assertNotDispatched(MessageUnsaved::class);
});
