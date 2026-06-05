<?php

use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Models\MessageAttachment;
use Syriable\Messenger\Models\MessageReaction;
use Syriable\Messenger\Models\MessageReport;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Models\SavedMessage;
use Syriable\Messenger\Pipelines\Send\EnsureAttachmentsAreValid;
use Syriable\Messenger\Pipelines\Send\EnsureConversationIsNotBlocked;
use Syriable\Messenger\Pipelines\Send\EnsureMessageHasContent;
use Syriable\Messenger\Pipelines\Send\EnsureParticipantsAreValid;
use Syriable\Messenger\Pipelines\Send\EnsureParticipantsExist;
use Syriable\Messenger\Pipelines\Send\EnsureReplyIsValid;
use Syriable\Messenger\Support\AuthParticipantResolver;
use Syriable\Messenger\Support\DefaultParticipantPresenter;
use Syriable\Messenger\Support\NullParticipantSearchResolver;
use Syriable\Messenger\Support\NullPresenceResolver;

// Configuration for syriable/laravel-messenger.
return [

    /*
    |--------------------------------------------------------------------------
    | Database table names
    |--------------------------------------------------------------------------
    |
    | The package prefixes its tables to avoid collisions with the host
    | application. The database always remains the source of truth.
    |
    */
    'tables' => [
        'conversations' => 'messenger_conversations',
        'participants' => 'messenger_participants',
        'messages' => 'messenger_messages',
        'attachments' => 'messenger_message_attachments',
        'reports' => 'messenger_message_reports',
        'saved' => 'messenger_saved_messages',
        'reactions' => 'messenger_message_reactions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Eloquent models
    |--------------------------------------------------------------------------
    |
    | Each model may be swapped for a host application subclass. Resolve models
    | through the Messenger model resolver rather than referencing them
    | directly so that overrides are always respected.
    |
    */
    'models' => [
        'conversation' => Conversation::class,
        'participant' => Participant::class,
        'message' => Message::class,
        'attachment' => MessageAttachment::class,
        'report' => MessageReport::class,
        'saved' => SavedMessage::class,
        'reaction' => MessageReaction::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Participant presenter
    |--------------------------------------------------------------------------
    |
    | Resolves a participant's display identity (name, avatar, handle, profile
    | URL, timezone) for any UI. The domain stores only the morph type and key,
    | so this is the single, swappable boundary for presentation. The default
    | reads conventional attributes (name, avatar_url, username, ...) and falls
    | back gracefully; point it at your own ParticipantPresenter implementation
    | to take full control.
    |
    */
    'presenter' => DefaultParticipantPresenter::class,

    /*
    |--------------------------------------------------------------------------
    | User interface
    |--------------------------------------------------------------------------
    |
    | Presentation settings for the bundled chat UI. These have no effect on the
    | headless domain; they configure the optional Livewire interface only.
    | theme: a `--msgr-*` token set ("neutral" | "dark" | a custom set you ship).
    | message_style: "flat" (email-like rows) or "bubble" (aligned chat bubbles).
    |
    */
    'ui' => [
        'enabled' => true,

        // The auth guard the default participant resolver reads (null = default).
        'guard' => null,

        // Resolves the participant whose inbox is shown. Swap for impersonation,
        // multi-guard or tenant-scoped contexts.
        'participant_resolver' => AuthParticipantResolver::class,

        // Resolves participant presence ("online" / "last seen"). The default
        // reports everyone offline; bind a presence-channel- or heartbeat-backed
        // resolver to light up the online dots.
        'presence_resolver' => NullPresenceResolver::class,

        // Resolves participants matching an inbox search term by name/handle.
        // The default matches none (search falls back to message bodies); bind
        // your own to search your user models.
        'search_resolver' => NullParticipantSearchResolver::class,

        'route' => [
            'prefix' => 'messages',
            'name' => 'messenger.',
            'middleware' => ['web', 'auth'],
        ],

        'theme' => 'neutral',
        'message_style' => 'flat',

        // Default composer behaviour: true = Enter sends (Shift+Enter = newline);
        // false = Enter = newline (Ctrl/Cmd+Enter sends). Users can toggle it.
        'enter_to_send' => true,

        // The emoji offered by the composer's emoji picker.
        'emoji' => ['😀', '😁', '😂', '🤣', '😊', '😍', '😘', '😎', '🤔', '😢', '😭', '😡', '👍', '👎', '🙏', '🎉', '❤️', '🔥', '💯', '✅'],

        // Messages loaded per infinite-scroll page (keyset pagination).
        'per_page' => 30,

        // Polling intervals used when realtime broadcasting is unavailable.
        'polling' => [
            'inbox' => '15s',
            'thread' => '5s',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    |
    | Constraints applied to outgoing message bodies by the send pipeline. Set
    | max_body_length to null to disable the length check.
    |
    */
    'messages' => [
        'max_body_length' => 20000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    |
    | Length limits for the optional reason/note fields on a message report.
    | Set a value to null to disable the corresponding check.
    |
    */
    'reports' => [
        'max_reason_length' => 255,
        'max_note_length' => 2000,
        // When true, only conversation participants may report a message.
        // Off by default: reporting is unrestricted to preserve the headless
        // contract — gate it in your application or enable this guard.
        'participants_only' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reactions
    |--------------------------------------------------------------------------
    |
    | The emoji a participant may react to a message with. An empty list allows
    | any emoji; a non-empty list is enforced by the react action and also drives
    | the UI picker.
    |
    */
    'reactions' => [
        'allowed' => ['👍', '❤️', '😂', '😮', '😢', '🙏'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Opt-in: when enabled, a listener notifies the recipient of each new message
    | via the configured channels. A recipient model may opt out per message by
    | implementing shouldReceiveMessengerNotification(Message): bool (mute is a
    | host concern). Disabled by default to preserve the headless contract.
    |
    */
    'notifications' => [
        'enabled' => false,
        'channels' => ['database'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation guards
    |--------------------------------------------------------------------------
    |
    | Opt-in guards enforced by the send pipeline. Off by default to preserve
    | the headless contract: the host decides who may message whom.
    |
    */
    'validation' => [
        // When true, the send pipeline rejects a sender/recipient that does not
        // exist in the database, preventing "ghost" participant rows whose
        // morphTo accessors would later resolve to null.
        'verify_participants_exist' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Attachment handling is fully self-contained: no external media packages
    | are required. Limits and mime rules are enforced by the send pipeline.
    |
    */
    'attachments' => [
        // Storage disk for attachment files. SECURITY: `$attachment->url` returns
        // an unsigned Storage::url(). For non-public files use a PRIVATE disk and
        // serve them through an authorized route (or temporaryUrl()); never point
        // this at a public disk for sensitive content.
        'disk' => env('MESSENGER_ATTACHMENT_DISK', 'local'),
        'directory' => 'messenger/attachments',
        // Maximum size per attachment, in kilobytes.
        'max_size' => 10240,
        // Reject empty (zero-byte) uploads. Set true to allow them.
        'allow_empty' => false,
        // Maximum number of attachments allowed on a single message.
        'max_per_message' => 10,
        // Allowed file extensions. Audio and video are intentionally excluded in v1.
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip'],
        // Allowed mime types.
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'application/zip',
            'application/x-zip-compressed',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Send pipeline
    |--------------------------------------------------------------------------
    |
    | The ordered list of pipes a message passes through before it is
    | persisted. Host applications may add, remove or reorder pipes to plug in
    | custom moderation, filtering or pre-send validation.
    |
    */
    'pipeline' => [
        EnsureParticipantsAreValid::class,
        EnsureParticipantsExist::class,
        EnsureConversationIsNotBlocked::class,
        EnsureMessageHasContent::class,
        EnsureAttachmentsAreValid::class,
        EnsureReplyIsValid::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Realtime broadcasting
    |--------------------------------------------------------------------------
    |
    | Broadcasting is optional and event-driven. The package functions fully
    | without it. When enabled, the bundled listeners broadcast domain events
    | over Laravel broadcasting (Reverb, Pusher, etc.).
    |
    */
    'broadcasting' => [
        'enabled' => env('MESSENGER_BROADCASTING_ENABLED', false),
        // Broadcast channel name prefix, e.g. "messenger.conversation.{id}".
        'channel_prefix' => 'messenger',
        // Use private channels (true) or presence/public channels semantics.
        'private' => true,
    ],
];
