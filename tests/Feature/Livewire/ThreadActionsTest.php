<?php

use Livewire\Livewire;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Livewire\Thread;
use Syriable\Messenger\Support\Models;
use Syriable\Messenger\Tests\Models\User;

/**
 * Conversation- and message-level actions exposed by the Thread island
 * (Epic E4 F4.6/F4.7, E3 F3.6). Each is a thin wrapper over a domain action and
 * announces `conversation-updated` so the sidebar and composer refresh.
 */
function threadActionSetup(): array
{
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($alice, $me, 'hello');

    return [$me, $alice, Messenger::between($me, $alice)];
}

it('stars and unstars the conversation', function () {
    [$me, $alice, $conversation] = threadActionSetup();

    $component = Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->call('toggleStar')
        ->assertDispatched('conversation-updated');

    expect($conversation->fresh()->participantFor($me)->starred_at)->not->toBeNull();

    $component->call('toggleStar');
    expect($conversation->fresh()->participantFor($me)->starred_at)->toBeNull();
});

it('archives and unarchives the conversation', function () {
    [$me, $alice, $conversation] = threadActionSetup();

    Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->call('toggleArchive');

    expect($conversation->fresh()->participantFor($me)->archived_at)->not->toBeNull();
});

it('marks the conversation as unread', function () {
    [$me, $alice, $conversation] = threadActionSetup();
    Messenger::markAsRead($conversation, $me);

    expect(Messenger::unreadCount($me))->toBe(0);

    Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->call('markUnread');

    expect(Messenger::unreadCount($me))->toBe(1);
});

it('clears the chat and empties the visible thread', function () {
    [$me, $alice, $conversation] = threadActionSetup();

    Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->assertSee('hello')
        ->call('clearChat')
        ->assertSet('messages', [])
        ->assertDontSee('hello');
});

it('blocks and unblocks the conversation', function () {
    [$me, $alice, $conversation] = threadActionSetup();

    $component = Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->call('toggleBlock');

    expect($conversation->fresh()->participantFor($me)->blocked_at)->not->toBeNull();

    $component->call('toggleBlock');
    expect($conversation->fresh()->participantFor($me)->blocked_at)->toBeNull();
});

it('moves the conversation to spam', function () {
    [$me, $alice, $conversation] = threadActionSetup();

    Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->call('moveToSpam')
        ->assertDispatched('conversation-updated');

    expect($conversation->fresh()->participantFor($me)->spammed_at)->not->toBeNull();
});

it('requests a reply with a quoted preview', function () {
    [$me, $alice, $conversation] = threadActionSetup();
    $message = Messenger::messages($conversation, $me)->first();

    Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->call('requestReply', $message->id)
        ->assertDispatched('reply-requested', messageId: $message->id, preview: 'hello');
});

it('reports a message', function () {
    [$me, $alice, $conversation] = threadActionSetup();
    $message = Messenger::messages($conversation, $me)->first();

    Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id])
        ->call('report', $message->id, 'spam')
        ->assertDispatched('message-reported', messageId: $message->id);

    expect(Models::report()::query()->where('message_id', $message->id)->count())->toBe(1);
});

it('does not act for a non-member', function () {
    [$me, $alice, $conversation] = threadActionSetup();
    $intruder = User::factory()->create(['name' => 'Intruder']);

    Livewire::actingAs($intruder)
        ->test(Thread::class)
        ->set('conversationId', $conversation->id)
        ->call('toggleStar')
        ->assertNotDispatched('conversation-updated');

    expect($conversation->fresh()->participantFor($me)->starred_at)->toBeNull();
});
