<?php

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Syriable\Messenger\Events\Broadcast\MessageSentBroadcast;
use Syriable\Messenger\Exceptions\ConversationBlockedException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Tests\Models\Agent;
use Syriable\Messenger\Tests\Models\User;

// Cross-model morph participants converse with each other.
it('lets a user and an agent (different morph types) message each other', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create();

    $message = $user->sendMessageTo($agent, 'Hi, I need help');
    $conversation = Messenger::between($user, $agent);

    expect($conversation)->not->toBeNull()
        ->and($conversation->participants)->toHaveCount(2)
        ->and($message->sender_type)->toBe($user->getMorphClass())
        ->and($message->conversation_id)->toBe($conversation->id);

    // The agent replies; it remains the same single conversation.
    $reply = $agent->sendMessageTo($user, 'Sure, how can I help?');

    expect($reply->conversation_id)->toBe($conversation->id)
        ->and(Messenger::between($agent, $user)->id)->toBe($conversation->id);
});

it('treats user->agent and agent->user as one direction-independent conversation', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create();

    Messenger::send($user, $agent, 'one');
    Messenger::send($agent, $user, 'two');

    expect(Messenger::between($user, $agent)->id)
        ->toBe(Messenger::between($agent, $user)->id);
});

it('keeps cross-model inboxes participant-specific', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create();

    Messenger::send($user, $agent, 'hello');

    expect($user->inbox())->toHaveCount(1)
        ->and($agent->inbox())->toHaveCount(1)
        ->and($agent->unreadMessagesCount())->toBe(1)
        ->and($user->unreadMessagesCount())->toBe(0);
});

// Spam / unspam end-to-end through the facade across model types.
it('blocks sending after spam and restores it after unspam (mutual, cross-model)', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create();

    Messenger::send($user, $agent, 'hi');
    $conversation = Messenger::between($user, $agent);

    Messenger::spam($conversation, $agent);

    // Mutual: neither side may send while spammed.
    expect(fn () => Messenger::send($user, $agent, 'still there?'))
        ->toThrow(ConversationBlockedException::class)
        ->and(fn () => Messenger::send($agent, $user, 'reply'))
        ->toThrow(ConversationBlockedException::class);

    // History is preserved.
    expect(Messenger::messages($conversation, $agent))->toHaveCount(1);

    Messenger::unspam($conversation, $agent);

    expect(Messenger::send($user, $agent, 'back now')->body)->toBe('back now');
});

// Broadcast contract: payload + channel naming with config overrides.
it('broadcasts on a private channel with the configured prefix and event name', function () {
    config()->set('messenger.broadcasting.private', true);
    config()->set('messenger.broadcasting.channel_prefix', 'chat');

    $user = User::factory()->create();
    $agent = Agent::factory()->create();
    $message = Messenger::send($user, $agent, 'hello');

    $event = new MessageSentBroadcast($message);
    $channels = $event->broadcastOn();

    expect($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-chat.conversation.'.$message->conversation_id)
        ->and($event->broadcastAs())->toBe('message.sent');

    $payload = $event->broadcastWith();
    expect($payload)->toHaveKeys(['id', 'conversation_id', 'sender_type', 'sender_id', 'body', 'reply_to_id', 'created_at'])
        ->and($payload['sender_type'])->toBe($user->getMorphClass())
        ->and($payload['body'])->toBe('hello');
});

it('broadcasts on a public channel when private is disabled', function () {
    config()->set('messenger.broadcasting.private', false);
    config()->set('messenger.broadcasting.channel_prefix', 'messenger');

    $user = User::factory()->create();
    $agent = Agent::factory()->create();
    $message = Messenger::send($user, $agent, 'hello');

    $channels = (new MessageSentBroadcast($message))->broadcastOn();

    expect($channels[0])->toBeInstanceOf(Channel::class)
        ->and($channels[0])->not->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('messenger.conversation.'.$message->conversation_id);
});
