<?php

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Syriable\Messenger\Events\Broadcast\MessageSentBroadcast;
use Syriable\Messenger\Events\MessageSent;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Listeners\BroadcastMessageSent;
use Syriable\Messenger\Tests\Models\User;

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
});

/*
 * Broadcasting disabled (the package default).
 */

it('does not broadcast MessageSentBroadcast on send when broadcasting is disabled by default', function () {
    expect(config('messenger.broadcasting.enabled', false))->toBeFalse();

    Event::fake();

    Messenger::send($this->alice, $this->bob, 'hello');

    // The domain event still fires...
    Event::assertDispatched(MessageSent::class);
    // ...but the listener is not registered, so no broadcast happens.
    Event::assertNotDispatched(MessageSentBroadcast::class);
});

/*
 * Listener behaviour (tested directly, independent of registration).
 */

it('broadcasts MessageSentBroadcast when the listener handles a MessageSent event', function () {
    $message = Messenger::send($this->alice, $this->bob, 'hello');

    Event::fake([MessageSentBroadcast::class]);

    (new BroadcastMessageSent)->handle(new MessageSent($message));

    Event::assertDispatched(MessageSentBroadcast::class, fn (MessageSentBroadcast $e) => (string) $e->message->getKey() === (string) $message->getKey());
});

/*
 * Broadcast event payload + channels.
 */

it('exposes the correct broadcastAs name', function () {
    $message = Messenger::send($this->alice, $this->bob, 'hello');

    expect((new MessageSentBroadcast($message))->broadcastAs())->toBe('message.sent');
});

it('builds broadcastWith with the expected keys and values', function () {
    $message = Messenger::send($this->alice, $this->bob, 'hello there');
    $message->refresh();

    $payload = (new MessageSentBroadcast($message))->broadcastWith();

    expect($payload)->toHaveKeys([
        'id', 'conversation_id', 'sender_type', 'sender_id', 'body', 'reply_to_id', 'created_at',
    ]);

    expect((string) $payload['id'])->toBe((string) $message->getKey());
    expect((string) $payload['conversation_id'])->toBe((string) $message->conversation_id);
    expect($payload['sender_type'])->toBe($message->sender_type);
    expect((string) $payload['sender_id'])->toBe((string) $message->sender_id);
    expect($payload['body'])->toBe('hello there');
    expect($payload['reply_to_id'])->toBe($message->reply_to_id);
    expect($payload['created_at'])->toBe(optional($message->created_at)->toIso8601String());
});

it('broadcasts on a private channel with the default prefix', function () {
    $message = Messenger::send($this->alice, $this->bob, 'hello');

    $channels = (new MessageSentBroadcast($message))->broadcastOn();

    expect($channels)->toHaveCount(1);
    $channel = $channels[0];

    expect($channel)->toBeInstanceOf(PrivateChannel::class);
    // PrivateChannel names are prefixed with "private-".
    expect($channel->name)->toBe('private-messenger.conversation.'.$message->conversation_id);
});

it('broadcasts on a public channel with a custom prefix when private is disabled', function () {
    config()->set('messenger.broadcasting.private', false);
    config()->set('messenger.broadcasting.channel_prefix', 'chat');

    $message = Messenger::send($this->alice, $this->bob, 'hello');

    $channels = (new MessageSentBroadcast($message))->broadcastOn();
    $channel = $channels[0];

    expect($channel)->toBeInstanceOf(Channel::class);
    expect($channel)->not->toBeInstanceOf(PrivateChannel::class);
    expect($channel->name)->toBe('chat.conversation.'.$message->conversation_id);
});
