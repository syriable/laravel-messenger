<?php

use Livewire\Livewire;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Livewire\Thread;
use Syriable\Messenger\Tests\Models\User;
use Syriable\Messenger\Tests\Support\FakeOnlinePresenceResolver;

/**
 * The Thread island (Epic E4): renders a conversation's visible messages,
 * bottom-anchored newest last, loads older pages via the keyset cursor, marks
 * the conversation read on open, and enforces membership.
 */
it('opens a conversation and shows its messages', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);

    Messenger::send($alice, $me, 'hello there');
    Messenger::send($me, $alice, 'hi alice');
    $conversation = Messenger::between($me, $alice);

    Livewire::actingAs($me)
        ->test(Thread::class)
        ->call('open', $conversation->id)
        ->assertSet('conversationId', $conversation->id)
        ->assertSee('hello there')
        ->assertSee('hi alice')
        ->assertSee('Alice'); // header
});

it('reacts to the conversation-selected event', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($alice, $me, 'ping');
    $conversation = Messenger::between($me, $alice);

    Livewire::actingAs($me)
        ->test(Thread::class)
        ->dispatch('conversation-selected', conversationId: $conversation->id)
        ->assertSet('conversationId', $conversation->id)
        ->assertSee('ping');
});

it('marks the conversation read on open and announces it', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($alice, $me, 'unread message');
    $conversation = Messenger::between($me, $alice);

    expect(Messenger::unreadCount($me))->toBe(1);

    Livewire::actingAs($me)
        ->test(Thread::class)
        ->call('open', $conversation->id)
        ->assertDispatched('conversation-read', conversationId: $conversation->id);

    expect(Messenger::unreadCount($me))->toBe(0);
});

it('paginates older messages via the keyset cursor', function () {
    config()->set('messenger.ui.per_page', 2);

    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);

    foreach (['m1', 'm2', 'm3', 'm4', 'm5'] as $body) {
        Messenger::send($alice, $me, $body);
    }
    $conversation = Messenger::between($me, $alice);

    $component = Livewire::actingAs($me)
        ->test(Thread::class)
        ->call('open', $conversation->id)
        ->assertSet('hasMoreOlder', true)
        ->assertSee('m4')
        ->assertSee('m5')
        ->assertDontSee('m3');

    $component->call('loadOlder')
        ->assertSee('m3')
        ->assertSee('m2')
        ->assertSee('m5'); // earlier page prepended, newest still present
});

it('does not open a conversation the participant is not part of', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    Messenger::send($alice, $bob, 'private between alice and bob');
    $conversation = Messenger::between($alice, $bob);

    Livewire::actingAs($me)
        ->test(Thread::class)
        ->call('open', $conversation->id)
        ->assertSet('conversationId', null)
        ->assertDontSee('private between alice and bob');
});

it('shows the empty state with no conversation selected', function () {
    $me = User::factory()->create(['name' => 'Me']);

    Livewire::actingAs($me)
        ->test(Thread::class)
        ->assertSee(__('messenger::ui.empty.inbox_title'));
});

it('appends new messages on the message-sent event', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($alice, $me, 'first');
    $conversation = Messenger::between($me, $alice);

    $component = Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->assertSee('first')
        ->assertDontSee('second');

    Messenger::send($alice, $me, 'second');

    $component->dispatch('message-sent', conversationId: $conversation->id)
        ->assertSee('first')
        ->assertSee('second');
});

it('shows the other participant presence in the header', function () {
    config()->set('messenger.ui.presence_resolver', FakeOnlinePresenceResolver::class);

    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($alice, $me, 'hi');
    $conversation = Messenger::between($me, $alice);

    Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->assertSee(__('messenger::ui.presence.online'));
});

it('shows and clears a typing indicator', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($alice, $me, 'hi');
    $conversation = Messenger::between($me, $alice);

    Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->call('showTyping', 'Alice')
        ->assertSee(__('messenger::ui.typing', ['name' => 'Alice']))
        ->call('clearTyping')
        ->assertSet('typingName', null);
});

it('polls for new messages when realtime is unavailable', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($alice, $me, 'first');
    $conversation = Messenger::between($me, $alice);

    $component = Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->assertDontSee('second');

    Messenger::send($alice, $me, 'second');

    $component->call('poll')->assertSee('second');
});

it('renders attachments and reply previews', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);

    $original = Messenger::send($alice, $me, 'the original message');
    Messenger::send($me, $alice, ['body' => 'a reply', 'reply_to' => $original->id]);
    $conversation = Messenger::between($me, $alice);

    Livewire::actingAs($me)
        ->test(Thread::class)
        ->call('open', $conversation->id)
        ->assertSee('a reply')
        ->assertSee('the original message'); // reply preview snippet
});
