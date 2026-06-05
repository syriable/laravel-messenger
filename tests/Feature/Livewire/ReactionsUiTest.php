<?php

use Livewire\Livewire;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Livewire\Thread;
use Syriable\Messenger\Support\Models;
use Syriable\Messenger\Tests\Models\User;

/**
 * The thread reaction affordance (E10): react/un-react from a message row and
 * render the reaction summary.
 */
function reactionSetup(): array
{
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($alice, $me, 'hello');
    $conversation = Messenger::between($me, $alice);
    $message = Messenger::messages($conversation, $me)->first();

    return [$me, $conversation, $message];
}

it('reacts to a message and renders the reaction', function () {
    [$me, $conversation, $message] = reactionSetup();

    Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->call('react', $message->id, '👍')
        ->assertSee('👍');

    expect(Models::reaction()::query()->where('message_id', $message->id)->count())->toBe(1);
});

it('toggles a reaction off', function () {
    [$me, $conversation, $message] = reactionSetup();

    Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->call('react', $message->id, '👍')
        ->call('react', $message->id, '👍');

    expect(Models::reaction()::query()->count())->toBe(0);
});

it('ignores a disallowed emoji without erroring', function () {
    [$me, $conversation, $message] = reactionSetup();

    Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->call('react', $message->id, '🤬')
        ->assertHasNoErrors();

    expect(Models::reaction()::query()->count())->toBe(0);
});

it('does not react for a non-member', function () {
    [$me, $conversation, $message] = reactionSetup();
    $intruder = User::factory()->create(['name' => 'Intruder']);

    Livewire::actingAs($intruder)
        ->test(Thread::class)
        ->set('conversationId', $conversation->id)
        ->call('react', $message->id, '👍');

    expect(Models::reaction()::query()->count())->toBe(0);
});
