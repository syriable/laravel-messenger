<?php

use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Queries\GetUnreadCountQuery;
use Syriable\Messenger\Tests\Models\User;

it('sums unread_count across all of the participant conversations', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();

    // a sends me 2 messages, b sends me 3 messages.
    Messenger::send($a, $me, 'a1');
    Messenger::send($a, $me, 'a2');
    Messenger::send($b, $me, 'b1');
    Messenger::send($b, $me, 'b2');
    Messenger::send($b, $me, 'b3');

    expect(app(GetUnreadCountQuery::class)->execute($me))->toBe(5);
});

it('counts the number of conversations with at least one unread message', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();
    $b = User::factory()->create();
    $c = User::factory()->create();

    Messenger::send($a, $me, 'a1');
    Messenger::send($a, $me, 'a2');
    Messenger::send($b, $me, 'b1');
    // c conversation initiated by me, so I have no unread there.
    Messenger::send($me, $c, 'c1');

    expect(app(GetUnreadCountQuery::class)->conversations($me))->toBe(2);
});

it('returns zero when there is nothing unread', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();

    // I send the message, so I have nothing unread.
    Messenger::send($me, $a, 'hello');

    expect(app(GetUnreadCountQuery::class)->execute($me))->toBe(0)
        ->and(app(GetUnreadCountQuery::class)->conversations($me))->toBe(0);
});

it('drops to zero after marking a conversation as read', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();

    Messenger::send($a, $me, 'one');
    Messenger::send($a, $me, 'two');

    expect(app(GetUnreadCountQuery::class)->execute($me))->toBe(2);

    $conv = Messenger::between($me, $a);
    Messenger::markAsRead($conv, $me);

    expect(app(GetUnreadCountQuery::class)->execute($me))->toBe(0)
        ->and(app(GetUnreadCountQuery::class)->conversations($me))->toBe(0);
});

it('is participant-specific', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // Alice sends Bob 3 messages: Bob has 3 unread, Alice has 0.
    Messenger::send($alice, $bob, 'one');
    Messenger::send($alice, $bob, 'two');
    Messenger::send($alice, $bob, 'three');

    expect(app(GetUnreadCountQuery::class)->execute($bob))->toBe(3)
        ->and(app(GetUnreadCountQuery::class)->execute($alice))->toBe(0)
        ->and(app(GetUnreadCountQuery::class)->conversations($bob))->toBe(1)
        ->and(app(GetUnreadCountQuery::class)->conversations($alice))->toBe(0);
});

it('exposes the same totals through the facade', function () {
    $me = User::factory()->create();
    $a = User::factory()->create();

    Messenger::send($a, $me, 'one');
    Messenger::send($a, $me, 'two');

    expect(Messenger::unreadCount($me))->toBe(2)
        ->and(Messenger::unreadConversations($me))->toBe(1);
});
