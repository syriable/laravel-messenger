<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Livewire\Composer;
use Syriable\Messenger\Tests\Models\User;

/**
 * The Composer island (Epic E5): sends text/attachments to the other
 * participant through the domain send pipeline, supports replies, disables on
 * block/spam, and announces `message-sent`.
 */
function composerConversation(User $me, User $other)
{
    Messenger::send($other, $me, 'opening message');

    return Messenger::between($me, $other);
}

it('sends a text message and announces it', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $conversation = composerConversation($me, $alice);

    Livewire::actingAs($me)
        ->test(Composer::class, ['conversationId' => $conversation->id])
        ->set('body', 'my reply')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('body', '')
        ->assertDispatched('message-sent', conversationId: $conversation->id);

    expect(Messenger::messages($conversation, $me)->pluck('body'))->toContain('my reply');
});

it('rejects an empty message', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $conversation = composerConversation($me, $alice);

    Livewire::actingAs($me)
        ->test(Composer::class, ['conversationId' => $conversation->id])
        ->call('send')
        ->assertHasErrors('body')
        ->assertNotDispatched('message-sent');
});

it('sends a reply referencing the original message', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $conversation = composerConversation($me, $alice);
    $original = Messenger::send($alice, $me, 'original message');

    Livewire::actingAs($me)
        ->test(Composer::class, ['conversationId' => $conversation->id])
        ->call('reply', $original->id, 'original message')
        ->assertSet('replyToId', $original->id)
        ->set('body', 'a threaded reply')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('replyToId', null);

    expect(Messenger::messages($conversation, $me)->last()->reply_to_id)->toBe($original->id);
});

it('cancels reply mode', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $conversation = composerConversation($me, $alice);

    Livewire::actingAs($me)
        ->test(Composer::class, ['conversationId' => $conversation->id])
        ->call('reply', 'some-id', 'preview')
        ->assertSet('replyToId', 'some-id')
        ->call('cancelReply')
        ->assertSet('replyToId', null)
        ->assertSet('replyPreview', null);
});

it('is locked and cannot send when the conversation is blocked', function () {
    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $conversation = composerConversation($me, $alice);
    Messenger::block($conversation, $me);

    $before = Messenger::messages($conversation, $me)->count();

    Livewire::actingAs($me)
        ->test(Composer::class, ['conversationId' => $conversation->id])
        ->assertSee(__('messenger::ui.composer.locked'))
        ->set('body', 'should not send')
        ->call('send')
        ->assertNotDispatched('message-sent');

    expect(Messenger::messages($conversation, $me)->count())->toBe($before);
});

it('sends a message with an image attachment', function () {
    Storage::fake(config('messenger.attachments.disk'));
    // Livewire's simulated upload does not preserve the client MIME type, so we
    // relax the MIME allow-list here to isolate the composer's behaviour
    // (forwarding attachments to send). MIME validation itself is covered by the
    // domain attachment tests.
    config()->set('messenger.attachments.allowed_mimes', []);

    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $conversation = composerConversation($me, $alice);

    Livewire::actingAs($me)
        ->test(Composer::class, ['conversationId' => $conversation->id])
        ->set('attachments', [UploadedFile::fake()->image('photo.jpg')])
        ->call('send')
        ->assertHasNoErrors()
        ->assertDispatched('message-sent');

    expect(Messenger::messages($conversation, $me)->last()->hasAttachments())->toBeTrue();
});

it('removes a staged attachment', function () {
    Storage::fake(config('messenger.attachments.disk'));

    $me = User::factory()->create(['name' => 'Me']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $conversation = composerConversation($me, $alice);

    Livewire::actingAs($me)
        ->test(Composer::class, ['conversationId' => $conversation->id])
        ->set('attachments', [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
        ])
        ->call('removeAttachment', 0)
        ->assertCount('attachments', 1);
});
