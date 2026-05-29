<?php

use Illuminate\Support\Str;
use Syriable\Messenger\Exceptions\InvalidReplyException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Tests\Models\User;

it('accepts a reply that points to a message in the same conversation', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $first = Messenger::send($alice, $bob, 'first');
    $reply = Messenger::send($bob, $alice, ['body' => 'reply', 'reply_to' => $first->getKey()]);

    expect($reply->reply_to_id)->toBe($first->getKey());
});

it('rejects a reply to a message that does not exist', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'first');

    Messenger::send($bob, $alice, ['body' => 'reply', 'reply_to' => (string) Str::ulid()]);
})->throws(InvalidReplyException::class);

it('rejects a reply to a message from a different conversation', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $carol = User::factory()->create();

    Messenger::send($alice, $bob, 'ab');
    $otherConversationMessage = Messenger::send($alice, $carol, 'ac');

    Messenger::send($alice, $bob, ['body' => 'x', 'reply_to' => $otherConversationMessage->getKey()]);
})->throws(InvalidReplyException::class);

it('rejects a reply on a brand-new conversation', function () {
    $alice = User::factory()->create();
    $dave = User::factory()->create();

    Messenger::send($alice, $dave, ['body' => 'hi', 'reply_to' => (string) Str::ulid()]);
})->throws(InvalidReplyException::class);
