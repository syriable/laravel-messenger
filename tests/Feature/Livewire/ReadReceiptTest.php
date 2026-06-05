<?php

use Livewire\Livewire;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Livewire\Thread;
use Syriable\Messenger\Tests\Models\User;

/**
 * Read receipts (E7 F7.1): the viewer's own messages show "Sent" until the
 * recipient's last_read_at reaches them, then "Read". Derived from the existing
 * last_read_at — no new state.
 */
function receiptStatus($component, string $body): ?string
{
    return collect($component->get('messages'))->firstWhere('body', $body)['status'] ?? null;
}

it('marks an own message as sent until the recipient reads it', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($me, $alice, 'hi alice');
    $conversation = Messenger::between($me, $alice);

    $component = Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id]);

    expect(receiptStatus($component, 'hi alice'))->toBe('sent');
});

it('marks an own message as read once the recipient reads it', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($me, $alice, 'hi alice');
    $conversation = Messenger::between($me, $alice);

    Messenger::markAsRead($conversation, $alice);

    $component = Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id]);

    expect(receiptStatus($component, 'hi alice'))->toBe('read');
});

it('does not put a read status on the other participant messages', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    Messenger::send($alice, $me, 'from alice');
    $conversation = Messenger::between($me, $alice);

    $component = Livewire::actingAs($me)
        ->test(Thread::class, ['conversationId' => $conversation->id]);

    expect(receiptStatus($component, 'from alice'))->toBeNull();
});
