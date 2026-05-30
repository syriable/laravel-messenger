<?php

use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Tests\Models\User;

// In-process stress: many first-message sends between the same pair must
// converge on exactly one conversation, never duplicating or throwing.
it('creates exactly one conversation under repeated first-message sends', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // 15 sends in quick succession; the first creates, the rest attach.
    for ($i = 0; $i < 15; $i++) {
        Messenger::send($alice, $bob, "message {$i}");
    }

    expect(Conversation::query()->count())->toBe(1);

    $conversation = Messenger::between($alice, $bob);
    expect(Messenger::messages($conversation, $bob))->toHaveCount(15)
        ->and($conversation->participants)->toHaveCount(2);
});

it('keeps unread accurate across a burst of inbound messages', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // Establish the conversation, then burst 10 messages at bob.
    Messenger::send($alice, $bob, 'first');

    for ($i = 0; $i < 10; $i++) {
        Messenger::send($alice, $bob, "burst {$i}");
    }

    // 11 received messages, none read.
    expect($bob->unreadMessagesCount())->toBe(11)
        ->and($bob->unreadConversationsCount())->toBe(1)
        // The sender never accrues unread for their own messages.
        ->and($alice->unreadMessagesCount())->toBe(0);

    // Reading clears it, and a further burst re-accrues exactly.
    $conversation = Messenger::between($alice, $bob);
    Messenger::markAsRead($conversation, $bob);
    expect($bob->unreadMessagesCount())->toBe(0);

    for ($i = 0; $i < 5; $i++) {
        Messenger::send($alice, $bob, "second burst {$i}");
    }

    expect($bob->unreadMessagesCount())->toBe(5);
});

it('preserves chronological order across many sends', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    for ($i = 0; $i < 12; $i++) {
        Messenger::send($alice, $bob, "ordered {$i}");
    }

    $conversation = Messenger::between($alice, $bob);
    $bodies = Messenger::messages($conversation, $bob)->pluck('body')->all();

    $expected = collect(range(0, 11))->map(fn ($i) => "ordered {$i}")->all();
    expect($bodies)->toBe($expected);
});
