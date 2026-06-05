<?php

use Illuminate\Support\Facades\Notification;
use Syriable\Messenger\Events\MessageSent;
use Syriable\Messenger\Facades\Messenger;
use Syriable\Messenger\Listeners\NotifyRecipient;
use Syriable\Messenger\Notifications\NewMessageNotification;
use Syriable\Messenger\Tests\Models\MutedUser;
use Syriable\Messenger\Tests\Models\User;

/**
 * Opt-in new-message notifications (#F1.9). The listener is only registered when
 * enabled, so its behaviour is exercised directly here (as with broadcasting).
 */
it('does not notify by default', function () {
    expect(config('messenger.notifications.enabled', false))->toBeFalse();

    Notification::fake();

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Messenger::send($alice, $bob, 'hello');

    Notification::assertNothingSent();
});

it('notifies the recipient when the listener handles a MessageSent event', function () {
    Notification::fake();

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $message = Messenger::send($alice, $bob, 'hello bob');

    (new NotifyRecipient)->handle(new MessageSent($message));

    Notification::assertSentTo(
        $bob,
        NewMessageNotification::class,
        fn (NewMessageNotification $n) => $n->message->is($message),
    );

    // The sender is never notified of their own message.
    Notification::assertNotSentTo($alice, NewMessageNotification::class);
});

it('respects a recipient that has muted notifications', function () {
    Notification::fake();

    $alice = User::factory()->create();
    $muted = MutedUser::query()->create(['name' => 'Muted']);
    $message = Messenger::send($alice, $muted, 'hello');

    (new NotifyRecipient)->handle(new MessageSent($message));

    Notification::assertNotSentTo($muted, NewMessageNotification::class);
});

it('builds the notification with the configured channels and payload', function () {
    config()->set('messenger.notifications.channels', ['database', 'mail']);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $message = Messenger::send($alice, $bob, 'the body');

    $notification = new NewMessageNotification($message);

    expect($notification->via($bob))->toBe(['database', 'mail']);

    $payload = $notification->toArray($bob);

    expect($payload)->toHaveKeys(['conversation_id', 'message_id', 'sender_type', 'sender_id', 'preview'])
        ->and((string) $payload['message_id'])->toBe((string) $message->getKey())
        ->and($payload['preview'])->toBe('the body');
});
