<?php

use Illuminate\Support\Facades\Event;
use Syriable\Messenger\Events\ConversationArchived;
use Syriable\Messenger\Events\ConversationBlocked;
use Syriable\Messenger\Events\ConversationCleared;
use Syriable\Messenger\Events\ConversationCreated;
use Syriable\Messenger\Events\ConversationMarkedAsSpam;
use Syriable\Messenger\Events\ConversationMarkedAsUnread;
use Syriable\Messenger\Events\ConversationRead;
use Syriable\Messenger\Events\ConversationStarred;
use Syriable\Messenger\Events\ConversationUnarchived;
use Syriable\Messenger\Events\ConversationUnblocked;
use Syriable\Messenger\Events\ConversationUnmarkedAsSpam;
use Syriable\Messenger\Events\ConversationUnstarred;
use Syriable\Messenger\Events\MessageReported;
use Syriable\Messenger\Events\MessageSent;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Models\MessageReport;
use Syriable\Messenger\Tests\Models\User;

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
});

/*
 * send / conversation lifecycle
 */

it('dispatches ConversationCreated and MessageSent when starting a new conversation', function () {
    Event::fake([ConversationCreated::class, MessageSent::class]);

    $message = Messenger::send($this->alice, $this->bob, 'hello');

    Event::assertDispatched(ConversationCreated::class, function (ConversationCreated $e) use ($message) {
        expect($e->conversation)->toBeInstanceOf(Conversation::class);

        return (string) $e->conversation->getKey() === (string) $message->conversation_id;
    });

    Event::assertDispatched(MessageSent::class, function (MessageSent $e) use ($message) {
        expect($e->message)->toBeInstanceOf(Message::class);

        return (string) $e->message->getKey() === (string) $message->getKey()
            && $e->message->body === 'hello';
    });

    Event::assertDispatchedTimes(ConversationCreated::class, 1);
    Event::assertDispatchedTimes(MessageSent::class, 1);
});

it('dispatches MessageSent only (not ConversationCreated) when sending into an existing conversation', function () {
    // First send creates the conversation.
    Messenger::send($this->alice, $this->bob, 'first');

    Event::fake([ConversationCreated::class, MessageSent::class]);

    $second = Messenger::send($this->alice, $this->bob, 'second');

    Event::assertNotDispatched(ConversationCreated::class);
    Event::assertDispatchedTimes(MessageSent::class, 1);
    Event::assertDispatched(MessageSent::class, fn (MessageSent $e) => $e->message->body === 'second'
        && (string) $e->message->getKey() === (string) $second->getKey());
});

/*
 * participant-state events
 */

function freshConversation(User $a, User $b): Conversation
{
    $message = Messenger::send($a, $b, 'seed');

    return Conversation::query()->findOrFail($message->conversation_id);
}

it('dispatches ConversationArchived with the actor participant', function () {
    $conversation = freshConversation($this->alice, $this->bob);

    Event::fake([ConversationArchived::class]);

    Messenger::archive($conversation, $this->alice);

    Event::assertDispatched(ConversationArchived::class, function (ConversationArchived $e) use ($conversation) {
        return (string) $e->participant->conversation_id === (string) $conversation->getKey()
            && $e->participant->represents($this->alice);
    });
});

it('dispatches ConversationUnarchived with the actor participant', function () {
    $conversation = freshConversation($this->alice, $this->bob);
    Messenger::archive($conversation, $this->alice);

    Event::fake([ConversationUnarchived::class]);

    Messenger::unarchive($conversation, $this->alice);

    Event::assertDispatched(ConversationUnarchived::class, fn (ConversationUnarchived $e) => (string) $e->participant->conversation_id === (string) $conversation->getKey()
        && $e->participant->represents($this->alice));
});

it('dispatches ConversationStarred with the actor participant', function () {
    $conversation = freshConversation($this->alice, $this->bob);

    Event::fake([ConversationStarred::class]);

    Messenger::star($conversation, $this->alice);

    Event::assertDispatched(ConversationStarred::class, fn (ConversationStarred $e) => (string) $e->participant->conversation_id === (string) $conversation->getKey()
        && $e->participant->represents($this->alice));
});

it('dispatches ConversationUnstarred with the actor participant', function () {
    $conversation = freshConversation($this->alice, $this->bob);
    Messenger::star($conversation, $this->alice);

    Event::fake([ConversationUnstarred::class]);

    Messenger::unstar($conversation, $this->alice);

    Event::assertDispatched(ConversationUnstarred::class, fn (ConversationUnstarred $e) => (string) $e->participant->conversation_id === (string) $conversation->getKey()
        && $e->participant->represents($this->alice));
});

it('dispatches ConversationBlocked with the actor participant', function () {
    $conversation = freshConversation($this->alice, $this->bob);

    Event::fake([ConversationBlocked::class]);

    Messenger::block($conversation, $this->alice);

    Event::assertDispatched(ConversationBlocked::class, fn (ConversationBlocked $e) => (string) $e->participant->conversation_id === (string) $conversation->getKey()
        && $e->participant->represents($this->alice));
});

it('dispatches ConversationUnblocked with the actor participant', function () {
    $conversation = freshConversation($this->alice, $this->bob);
    Messenger::block($conversation, $this->alice);

    Event::fake([ConversationUnblocked::class]);

    Messenger::unblock($conversation, $this->alice);

    Event::assertDispatched(ConversationUnblocked::class, fn (ConversationUnblocked $e) => (string) $e->participant->conversation_id === (string) $conversation->getKey()
        && $e->participant->represents($this->alice));
});

it('dispatches ConversationMarkedAsSpam with the actor participant', function () {
    $conversation = freshConversation($this->alice, $this->bob);

    Event::fake([ConversationMarkedAsSpam::class]);

    Messenger::spam($conversation, $this->alice);

    Event::assertDispatched(ConversationMarkedAsSpam::class, fn (ConversationMarkedAsSpam $e) => (string) $e->participant->conversation_id === (string) $conversation->getKey()
        && $e->participant->represents($this->alice));
});

it('dispatches ConversationUnmarkedAsSpam with the actor participant', function () {
    $conversation = freshConversation($this->alice, $this->bob);
    Messenger::spam($conversation, $this->alice);

    Event::fake([ConversationUnmarkedAsSpam::class]);

    Messenger::unspam($conversation, $this->alice);

    Event::assertDispatched(ConversationUnmarkedAsSpam::class, fn (ConversationUnmarkedAsSpam $e) => (string) $e->participant->conversation_id === (string) $conversation->getKey()
        && $e->participant->represents($this->alice));
});

it('dispatches ConversationCleared with the actor participant', function () {
    $conversation = freshConversation($this->alice, $this->bob);

    Event::fake([ConversationCleared::class]);

    Messenger::clear($conversation, $this->alice);

    Event::assertDispatched(ConversationCleared::class, fn (ConversationCleared $e) => (string) $e->participant->conversation_id === (string) $conversation->getKey()
        && $e->participant->represents($this->alice));
});

it('dispatches ConversationRead with the actor participant', function () {
    $conversation = freshConversation($this->alice, $this->bob);

    Event::fake([ConversationRead::class]);

    Messenger::markAsRead($conversation, $this->bob);

    Event::assertDispatched(ConversationRead::class, fn (ConversationRead $e) => (string) $e->participant->conversation_id === (string) $conversation->getKey()
        && $e->participant->represents($this->bob));
});

it('dispatches ConversationMarkedAsUnread with the actor participant', function () {
    $conversation = freshConversation($this->alice, $this->bob);
    Messenger::markAsRead($conversation, $this->bob);

    Event::fake([ConversationMarkedAsUnread::class]);

    Messenger::markAsUnread($conversation, $this->bob);

    Event::assertDispatched(ConversationMarkedAsUnread::class, fn (ConversationMarkedAsUnread $e) => (string) $e->participant->conversation_id === (string) $conversation->getKey()
        && $e->participant->represents($this->bob));
});

/*
 * report
 */

it('dispatches MessageReported with the report payload', function () {
    $message = Messenger::send($this->alice, $this->bob, 'spammy');

    Event::fake([MessageReported::class]);

    $report = Messenger::report($message, $this->bob, 'spam', 'a note');

    Event::assertDispatched(MessageReported::class, function (MessageReported $e) use ($report, $message) {
        expect($e->report)->toBeInstanceOf(MessageReport::class);

        return (string) $e->report->getKey() === (string) $report->getKey()
            && (string) $e->report->message_id === (string) $message->getKey()
            && $e->report->reason === 'spam';
    });
});
