<?php

use Syriable\Messenger\Models\Conversation;
use Syriable\Messenger\Models\Message;
use Syriable\Messenger\Models\MessageAttachment;
use Syriable\Messenger\Models\MessageReport;
use Syriable\Messenger\Models\Participant;
use Syriable\Messenger\Pipelines\Send\EnsureAttachmentsAreValid;
use Syriable\Messenger\Pipelines\Send\EnsureConversationIsNotBlocked;
use Syriable\Messenger\Pipelines\Send\EnsureMessageHasContent;
use Syriable\Messenger\Pipelines\Send\EnsureParticipantsAreValid;
use Syriable\Messenger\Pipelines\Send\EnsureParticipantsExist;
use Syriable\Messenger\Pipelines\Send\EnsureReplyIsValid;

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
        'disk' => env('MESSENGER_ATTACHMENT_DISK', 'local'),
        'directory' => 'messenger/attachments',
        // Maximum size per attachment, in kilobytes.
        'max_size' => 10240,
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
