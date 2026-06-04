<?php

use Illuminate\Support\Carbon;
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

/**
 * Send at a controllable time so message ordering is deterministic for keyset
 * pagination assertions, instead of depending on sub-microsecond timing.
 */
function threadSendAt(User $from, User $to, string $body, Carbon $at): void
{
    Carbon::setTestNow($at);
    Messenger::send($from, $to, $body);
    Carbon::setTestNow();
}

afterEach(fn () => Carbon::setTestNow());
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

    $base = now()->subMinutes(10);
    foreach (['alpha', 'bravo', 'charlie', 'delta', 'echo'] as $i => $body) {
        threadSendAt($alice, $me, $body, $base->copy()->addMinutes($i));
    }
    $conversation = Messenger::between($me, $alice);

    // Assert on the component's message state (exact, ordered bodies) rather
    // than the rendered HTML: a short token can collide with the lowercase ULIDs
    // in Livewire's snapshot, which made assertDontSee() flaky.
    $bodies = fn ($component) => collect($component->get('messages'))->pluck('body')->all();

    $component = Livewire::actingAs($me)
        ->test(Thread::class)
        ->call('open', $conversation->id)
        ->assertSet('hasMoreOlder', true);

    expect($bodies($component))->toBe(['delta', 'echo']); // latest page, chronological

    $component->call('loadOlder');

    expect($bodies($component))->toBe(['bravo', 'charlie', 'delta', 'echo']); // older page prepended
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
    threadSendAt($alice, $me, 'first', now()->subMinutes(2));
    $conversation = Messenger::between($me, $alice);

    $component = Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->assertSee('first')
        ->assertDontSee('second');

    threadSendAt($alice, $me, 'second', now()->subMinute());

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
    threadSendAt($alice, $me, 'first', now()->subMinutes(2));
    $conversation = Messenger::between($me, $alice);

    $component = Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->assertDontSee('second');

    threadSendAt($alice, $me, 'second', now()->subMinute());

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
