<?php

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Syriable\Messenger\Events\Broadcast\ConversationReadBroadcast;
use Syriable\Messenger\Events\ConversationRead;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Listeners\BroadcastConversationRead;
use Syriable\Messenger\Tests\Models\User;

beforeEach(function () {
    $this->me = User::factory()->create();
    $this->alice = User::factory()->create();
    Messenger::send($this->alice, $this->me, 'hello');
    $this->conversation = Messenger::between($this->me, $this->alice);
});

it('does not broadcast a read receipt when broadcasting is disabled by default', function () {
    expect(config('messenger.broadcasting.enabled', false))->toBeFalse();

    Event::fake();

    Messenger::markAsRead($this->conversation, $this->me);

    Event::assertDispatched(ConversationRead::class);
    Event::assertNotDispatched(ConversationReadBroadcast::class);
});

it('broadcasts a read receipt when the listener handles a ConversationRead event', function () {
    $participant = Messenger::markAsRead($this->conversation, $this->me);

    Event::fake([ConversationReadBroadcast::class]);

    (new BroadcastConversationRead)->handle(new ConversationRead($participant));

    Event::assertDispatched(
        ConversationReadBroadcast::class,
        fn (ConversationReadBroadcast $e) => $e->participant->is($participant),
    );
});

it('exposes the correct broadcastAs name', function () {
    $participant = Messenger::markAsRead($this->conversation, $this->me);

    expect((new ConversationReadBroadcast($participant))->broadcastAs())->toBe('conversation.read');
});

it('builds broadcastWith with the expected keys', function () {
    $participant = Messenger::markAsRead($this->conversation, $this->me);

    $payload = (new ConversationReadBroadcast($participant))->broadcastWith();

    expect($payload)->toHaveKeys(['conversation_id', 'participant_type', 'participant_id', 'read_at'])
        ->and((string) $payload['conversation_id'])->toBe((string) $this->conversation->id)
        ->and($payload['participant_type'])->toBe($this->me->getMorphClass())
        ->and((string) $payload['participant_id'])->toBe((string) $this->me->getKey())
        ->and($payload['read_at'])->not->toBeNull();
});

it('broadcasts on a private conversation channel with the default prefix', function () {
    $participant = Messenger::markAsRead($this->conversation, $this->me);

    $channels = (new ConversationReadBroadcast($participant))->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-messenger.conversation.'.$this->conversation->id);
});

it('broadcasts on a public channel when private is disabled', function () {
    config()->set('messenger.broadcasting.private', false);

    $participant = Messenger::markAsRead($this->conversation, $this->me);
    $channel = (new ConversationReadBroadcast($participant))->broadcastOn()[0];

    expect($channel)->toBeInstanceOf(Channel::class)
        ->and($channel)->not->toBeInstanceOf(PrivateChannel::class);
});
