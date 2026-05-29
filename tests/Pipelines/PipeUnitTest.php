<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Syriable\Messenger\Data\NewMessage;
use Syriable\Messenger\Data\PendingMessage;
use Syriable\Messenger\Exceptions\ConversationBlockedException;
use Syriable\Messenger\Exceptions\InvalidAttachmentException;
use Syriable\Messenger\Exceptions\InvalidMessageException;
use Syriable\Messenger\Exceptions\InvalidParticipantException;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Pipelines\Send\EnsureAttachmentsAreValid;
use Syriable\Messenger\Pipelines\Send\EnsureConversationIsNotBlocked;
use Syriable\Messenger\Pipelines\Send\EnsureMessageHasContent;
use Syriable\Messenger\Pipelines\Send\EnsureParticipantsAreValid;
use Syriable\Messenger\Tests\Models\User;

/**
 * The identity "next" closure used to drive a single pipe in isolation.
 */
function passthrough(): Closure
{
    return fn (PendingMessage $message) => $message;
}

function pending(User $sender, User $recipient, NewMessage $message, $conversation = null): PendingMessage
{
    return new PendingMessage($sender, $recipient, $message, $conversation);
}

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
});

/*
|--------------------------------------------------------------------------
| EnsureParticipantsAreValid
|--------------------------------------------------------------------------
*/

it('EnsureParticipantsAreValid passes distinct participants through unchanged', function () {
    $message = pending($this->alice, $this->bob, new NewMessage('hi'));

    $result = (new EnsureParticipantsAreValid)->handle($message, passthrough());

    expect($result)->toBe($message);
});

it('EnsureParticipantsAreValid throws when sender and recipient are the same participant', function () {
    $message = pending($this->alice, $this->alice, new NewMessage('hi'));

    (new EnsureParticipantsAreValid)->handle($message, passthrough());
})->throws(InvalidParticipantException::class);

/*
|--------------------------------------------------------------------------
| EnsureMessageHasContent
|--------------------------------------------------------------------------
*/

it('EnsureMessageHasContent passes a body-only message', function () {
    $message = pending($this->alice, $this->bob, new NewMessage('hello'));

    expect((new EnsureMessageHasContent)->handle($message, passthrough()))->toBe($message);
});

it('EnsureMessageHasContent passes an attachments-only message', function () {
    Storage::fake('local');
    $message = pending($this->alice, $this->bob, new NewMessage(null, [UploadedFile::fake()->image('a.jpg')]));

    expect((new EnsureMessageHasContent)->handle($message, passthrough()))->toBe($message);
});

it('EnsureMessageHasContent throws on an empty message', function () {
    $message = pending($this->alice, $this->bob, new NewMessage(null, []));

    (new EnsureMessageHasContent)->handle($message, passthrough());
})->throws(InvalidMessageException::class);

/*
|--------------------------------------------------------------------------
| EnsureAttachmentsAreValid
|--------------------------------------------------------------------------
*/

it('EnsureAttachmentsAreValid passes when there are no attachments', function () {
    $message = pending($this->alice, $this->bob, new NewMessage('text only'));

    expect((new EnsureAttachmentsAreValid)->handle($message, passthrough()))->toBe($message);
});

it('EnsureAttachmentsAreValid passes valid attachments', function () {
    Storage::fake('local');
    $message = pending($this->alice, $this->bob, new NewMessage(null, [
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
    ]));

    expect((new EnsureAttachmentsAreValid)->handle($message, passthrough()))->toBe($message);
});

it('EnsureAttachmentsAreValid throws on too many attachments', function () {
    config()->set('messenger.attachments.max_per_message', 1);
    $message = pending($this->alice, $this->bob, new NewMessage(null, [
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.jpg'),
    ]));

    (new EnsureAttachmentsAreValid)->handle($message, passthrough());
})->throws(InvalidAttachmentException::class, 'more than 1');

it('EnsureAttachmentsAreValid throws on an oversized attachment', function () {
    config()->set('messenger.attachments.max_size', 1024);
    $message = pending($this->alice, $this->bob, new NewMessage(null, [
        UploadedFile::fake()->create('big.jpg', 20000, 'image/jpeg'),
    ]));

    (new EnsureAttachmentsAreValid)->handle($message, passthrough());
})->throws(InvalidAttachmentException::class, 'exceeds the maximum size');

it('EnsureAttachmentsAreValid throws on a disallowed type', function () {
    $message = pending($this->alice, $this->bob, new NewMessage(null, [
        UploadedFile::fake()->create('evil.exe', 1, 'application/x-msdownload'),
    ]));

    (new EnsureAttachmentsAreValid)->handle($message, passthrough());
})->throws(InvalidAttachmentException::class, 'not allowed');

/*
|--------------------------------------------------------------------------
| EnsureConversationIsNotBlocked
|--------------------------------------------------------------------------
*/

it('EnsureConversationIsNotBlocked passes when the conversation is null (new)', function () {
    $message = pending($this->alice, $this->bob, new NewMessage('hi'), null);

    expect((new EnsureConversationIsNotBlocked)->handle($message, passthrough()))->toBe($message);
});

it('EnsureConversationIsNotBlocked passes when no participant is blocking or spamming', function () {
    Messenger::send($this->alice, $this->bob, 'hi');
    $conversation = Messenger::between($this->alice, $this->bob);

    $message = pending($this->alice, $this->bob, new NewMessage('again'), $conversation);

    expect((new EnsureConversationIsNotBlocked)->handle($message, passthrough()))->toBe($message);
});

it('EnsureConversationIsNotBlocked throws when a participant is blocking', function () {
    Messenger::send($this->alice, $this->bob, 'hi');
    $conversation = Messenger::between($this->alice, $this->bob);
    Messenger::block($conversation, $this->alice);

    $conversation = Messenger::between($this->alice, $this->bob)->load('participants');
    $message = pending($this->bob, $this->alice, new NewMessage('still there?'), $conversation);

    (new EnsureConversationIsNotBlocked)->handle($message, passthrough());
})->throws(ConversationBlockedException::class);

it('EnsureConversationIsNotBlocked throws when a participant is spamming', function () {
    Messenger::send($this->alice, $this->bob, 'hi');
    $conversation = Messenger::between($this->alice, $this->bob);
    Messenger::spam($conversation, $this->alice);

    $conversation = Messenger::between($this->alice, $this->bob)->load('participants');
    $message = pending($this->bob, $this->alice, new NewMessage('spammy?'), $conversation);

    (new EnsureConversationIsNotBlocked)->handle($message, passthrough());
})->throws(ConversationBlockedException::class);
