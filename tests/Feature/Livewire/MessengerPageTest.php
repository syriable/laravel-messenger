<?php

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Livewire\Composer;
use Syriable\Messenger\Livewire\Messenger as MessengerPage;
use Syriable\Messenger\Livewire\Sidebar;
use Syriable\Messenger\Livewire\Thread;
use Syriable\Messenger\Tests\Models\User;

/**
 * The full-page root composes the islands into the shell and owns the
 * URL-addressable conversation selection (Epic E2/E3).
 */
it('renders the sidebar island and the empty state with no selection', function () {
    $me = User::factory()->create(['name' => 'Me']);

    Livewire::actingAs($me)
        ->test(MessengerPage::class)
        ->assertSet('conversation', null)
        ->assertSeeLivewire(Sidebar::class)
        ->assertSee(__('messenger::ui.empty.inbox_title'));
});

it('mounts the thread and composer once a conversation is selected', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($alice, $me, 'hello');
    $conversation = Messenger::between($me, $alice);

    Livewire::actingAs($me)
        ->test(MessengerPage::class)
        ->dispatch('conversation-selected', conversationId: $conversation->id)
        ->assertSet('conversation', $conversation->id)
        ->assertSeeLivewire(Thread::class)
        ->assertSeeLivewire(Composer::class);
});

it('accepts a conversation via mount for deep-linking', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($alice, $me, 'hello');
    $conversation = Messenger::between($me, $alice);

    Livewire::actingAs($me)
        ->test(MessengerPage::class, ['conversation' => $conversation->id])
        ->assertSet('conversation', $conversation->id)
        ->assertSeeLivewire(Thread::class);
});

it('registers the full-page messenger route', function () {
    expect(Route::has('messenger.index'))->toBeTrue();
});
