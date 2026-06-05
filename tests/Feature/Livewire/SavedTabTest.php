<?php

use Livewire\Livewire;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Livewire\Thread;
use Syriable\Messenger\Tests\Models\User;

/**
 * The Saved tab and per-message Save action (Epic E4 F4.6 + E8 F8.2), wired to
 * the saved-messages domain feature.
 */
function savedTabSetup(): array
{
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($alice, $me, 'a saved message');
    $conversation = Messenger::between($me, $alice);
    $message = Messenger::messages($conversation, $me)->first();

    return [$me, $conversation, $message];
}

it('saves and unsaves a message from the thread', function () {
    [$me, $conversation, $message] = savedTabSetup();

    $component = Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->call('toggleSave', $message->id);

    expect(Messenger::isSaved($message, $me))->toBeTrue();

    $component->call('toggleSave', $message->id);

    expect(Messenger::isSaved($message, $me))->toBeFalse();
});

it('switches between the messages and saved tabs', function () {
    [$me, $conversation] = savedTabSetup();

    Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->assertSet('tab', 'messages')
        ->call('switchTab', 'saved')
        ->assertSet('tab', 'saved')
        ->call('switchTab', 'bogus')
        ->assertSet('tab', 'messages');
});

it('renders saved messages on the saved tab and empty state otherwise', function () {
    [$me, $conversation, $message] = savedTabSetup();

    Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->call('switchTab', 'saved')
        ->assertSee(__('messenger::ui.empty.saved'))
        ->call('toggleSave', $message->id)
        ->assertSee('a saved message');
});
