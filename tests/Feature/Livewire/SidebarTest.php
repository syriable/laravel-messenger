<?php

use Livewire\Livewire;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Livewire\Sidebar;
use Syriable\Messenger\Tests\Models\User;
use Syriable\Messenger\Tests\Support\UserNameSearchResolver;

/**
 * The Sidebar conversation-list island (Epic E3). It reads the current
 * participant's inbox through the domain query layer, resolves display names via
 * the presenter, filters by scope/search, and emits selection events.
 */
it('lists the current participant inbox with names and snippets', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    Messenger::send($alice, $me, 'hello from alice');
    Messenger::send($bob, $me, 'hello from bob');

    Livewire::actingAs($me)
        ->test(Sidebar::class)
        ->assertSee('Alice')
        ->assertSee('Bob')
        ->assertSee('hello from bob');
});

it('shows the empty state when there is no current participant', function () {
    // No authenticated user → resolver returns null → empty list.
    Livewire::test(Sidebar::class)
        ->assertSee(__('messenger::ui.empty.list'));
});

it('filters the list to unread conversations', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    Messenger::send($alice, $me, 'a');
    Messenger::send($bob, $me, 'b');

    Messenger::markAsRead(Messenger::between($me, $alice), $me);

    Livewire::actingAs($me)
        ->test(Sidebar::class)
        ->call('setScope', 'unread')
        ->assertSee('Bob')
        ->assertDontSee('Alice');
});

it('filters the list to starred conversations', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    Messenger::send($alice, $me, 'a');
    Messenger::send($bob, $me, 'b');
    Messenger::star(Messenger::between($me, $alice), $me);

    Livewire::actingAs($me)
        ->test(Sidebar::class)
        ->call('setScope', 'starred')
        ->assertSee('Alice')
        ->assertDontSee('Bob');
});

it('searches by participant name via the bound resolver', function () {
    config()->set('messenger.ui.search_resolver', UserNameSearchResolver::class);

    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    Messenger::send($alice, $me, 'apples');
    Messenger::send($bob, $me, 'bananas');

    Livewire::actingAs($me)
        ->test(Sidebar::class)
        ->set('search', 'Alice')
        ->assertSee('Alice')
        ->assertDontSee('Bob');
});

it('searches by message body', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    Messenger::send($alice, $me, 'the quarterly report');
    Messenger::send($bob, $me, 'lunch plans');

    Livewire::actingAs($me)
        ->test(Sidebar::class)
        ->set('search', 'quarterly')
        ->assertSee('Alice')
        ->assertDontSee('Bob');
});

it('dispatches a selection event and tracks the active conversation', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);

    Messenger::send($alice, $me, 'a');
    $conversation = Messenger::between($me, $alice);

    Livewire::actingAs($me)
        ->test(Sidebar::class)
        ->call('select', $conversation->id)
        ->assertSet('activeConversationId', $conversation->id)
        ->assertDispatched('conversation-selected', conversationId: $conversation->id);
});

it('ignores an unknown scope and falls back to all', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($alice, $me, 'a');

    Livewire::actingAs($me)
        ->test(Sidebar::class)
        ->call('setScope', 'bogus')
        ->assertSet('scope', 'all')
        ->assertSee('Alice');
});
