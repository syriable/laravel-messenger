<?php

// English UI strings for the Laravel Messenger interface. Publish & translate
// with `php artisan vendor:publish --tag="messenger-translations"`.

return [
    'conversations' => 'Conversations',
    'conversation' => 'Conversation',
    'about' => 'About',
    'star' => 'Star conversation',
    'unstar' => 'Unstar conversation',
    'unread_messages' => '{1} :count unread message|[2,*] :count unread messages',
    'search' => 'Search messages',
    'search_placeholder' => 'Search…',
    'you_prefix' => 'You:',
    'you' => 'You',
    'no_messages_yet' => 'No messages yet',
    'unknown_participant' => 'Unknown',
    'load_earlier' => 'Load earlier messages',
    'attachment' => 'Attachment',
    'last_seen' => 'Last seen :time',
    'typing' => ':name is typing…',

    'menu' => [
        'conversation' => 'Conversation actions',
        'message' => 'Message actions',
        'mark_unread' => 'Mark as unread',
        'archive' => 'Move to archive',
        'unarchive' => 'Move out of archive',
        'block' => 'Block',
        'unblock' => 'Unblock',
        'clear' => 'Clear chat',
        'reply' => 'Reply',
        'report' => 'Report',
        'spambox' => 'Move to spambox',
    ],

    'composer' => [
        'placeholder' => 'Type a message…',
        'send' => 'Send',
        'attach' => 'Attach a file',
        'remove_attachment' => 'Remove attachment',
        'empty' => 'Write a message or attach a file.',
        'locked' => 'You can no longer send messages in this conversation.',
        'replying' => 'Replying to a message',
        'cancel_reply' => 'Cancel reply',
    ],

    'filter' => [
        'label' => 'Filter conversations',
        'all' => 'All',
        'unread' => 'Unread',
        'starred' => 'Starred',
        'archived' => 'Archived',
        'spam' => 'Spam',
    ],

    'presence' => [
        'online' => 'Online',
        'away' => 'Away',
        'offline' => 'Offline',
    ],

    'empty' => [
        'inbox_title' => 'Pick up where you left off',
        'inbox_subtitle' => 'Select a conversation and chat away.',
        'list' => 'No conversations yet.',
        'no_results' => 'No conversations match your search.',
    ],
];
