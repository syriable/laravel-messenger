<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Messenger broadcast channels
|--------------------------------------------------------------------------
|
| Authorization for the realtime channels the UI subscribes to. These are
| published (not auto-registered) so the host stays in control of how a
| participant's identity maps onto the authenticated user.
|
| Publish with: php artisan vendor:publish --tag="messenger-channels"
| then require this file from your routes/channels.php (or register the
| channels directly there).
|
| The default maps the channel's participant to the authenticated user. If
| your participants are not always the auth user (e.g. an admin acting as a
| support agent), replace the authorization closures accordingly.
|
*/

$prefix = config('messenger.broadcasting.channel_prefix', 'messenger');

// Per-conversation channel: new messages, read receipts and typing. Authorize
// only the two participants of the conversation.
Broadcast::channel($prefix.'.conversation.{conversationId}', function ($user, string $conversationId) {
    $participant = config('messenger.models.participant');

    return $participant::query()
        ->where('conversation_id', $conversationId)
        ->where('participant_type', $user->getMorphClass())
        ->where('participant_id', $user->getKey())
        ->exists();
});

// Per-user channel: inbox-level signals (new conversation, unread changes,
// cross-device sync). Authorize only that user.
Broadcast::channel($prefix.'.user.{type}.{id}', function ($user, string $type, string $id) {
    return $user->getMorphClass() === $type && (string) $user->getKey() === $id;
});

// Presence channel: who is online. Returns a small identity payload used by the
// default PresenceResolver.
Broadcast::channel($prefix.'.presence.{scope}', function ($user, string $scope) {
    return [
        'type' => $user->getMorphClass(),
        'id' => (string) $user->getKey(),
    ];
});
