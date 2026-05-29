<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Events\ConversationCreated;
use Syriable\Messenger\Events\MessageSent;
use Syriable\Messenger\Exceptions\ConversationBlockedException;
use Syriable\Messenger\Exceptions\InvalidMessageException;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Tests\Models\User;

it('creates a conversation lazily on the first message', function () {
    Event::fake([ConversationCreated::class, MessageSent::class]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    expect(Conversation::count())->toBe(0);

    $message = Messenger::send($alice, $bob, 'Hello Bob');

    expect(Conversation::count())->toBe(1)
        ->and($message->body)->toBe('Hello Bob')
        ->and($message->conversation->participants)->toHaveCount(2);

    Event::assertDispatched(ConversationCreated::class);
    Event::assertDispatched(MessageSent::class);
});

it('reuses the single conversation between two participants regardless of direction', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $first = Messenger::send($alice, $bob, 'Hi');
    $second = Messenger::send($bob, $alice, 'Hey back');

    expect(Conversation::count())->toBe(1)
        ->and($second->conversation_id)->toBe($first->conversation_id);
});

it('rejects an empty message', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, ['body' => '   ']);
})->throws(InvalidMessageException::class);

it('forbids messaging yourself', function () {
    $alice = User::factory()->create();

    Messenger::send($alice, $alice, 'me');
})->throws(InvalidParticipantException::class);

it('increments the recipient unread counter and reads for the sender', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'one');
    $conversation = Messenger::between($alice, $bob);

    expect($conversation->participantFor($bob)->unread_count)->toBe(1)
        ->and($conversation->participantFor($alice)->unread_count)->toBe(0);
});

it('prevents sending when the conversation is blocked, mutually', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Messenger::send($alice, $bob, 'hi');
    $conversation = Messenger::between($alice, $bob);

    Messenger::block($conversation, $alice);

    // Neither side may send.
    expect(fn () => Messenger::send($bob, $alice, 'still there?'))
        ->toThrow(ConversationBlockedException::class);
});

it('stores attachments for a message', function () {
    Storage::fake('local');

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $message = Messenger::send($alice, $bob, [
        'attachments' => [UploadedFile::fake()->image('photo.jpg')],
    ]);

    expect($message->attachments)->toHaveCount(1);
    Storage::disk('local')->assertExists($message->attachments->first()->path);
});
