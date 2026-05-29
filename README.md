# Laravel Messenger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/syriable/laravel-messenger.svg?style=flat-square)](https://packagist.org/packages/syriable/laravel-messenger)
[![Tests](https://img.shields.io/github/actions/workflow/status/syriable/laravel-messenger/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/syriable/laravel-messenger/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/syriable/laravel-messenger.svg?style=flat-square)](https://packagist.org/packages/syriable/laravel-messenger)

A **headless, backend-only** one-to-one messaging domain platform for Laravel. Think Facebook Messenger / Instagram DMs / WhatsApp direct messages — not support tickets, channels or forums.

It is **Laravel-native, event-driven, performance-oriented and extensible by composition**. It ships **no UI, controllers, routes, policies or assets** — your application owns presentation and authorization; the package owns the messaging domain.

## Features

- 💬 **One-to-one conversations** — exactly one persistent conversation between any two participants, created lazily on the first message.
- 👤 **Morphable participants** — users, admins, sellers, support agents… any Eloquent model.
- 📎 **Attachments** — first-class upload lifecycle, storage, validation and metadata (images, PDFs, zips). No external media packages.
- ↩️ **Lightweight replies** — WhatsApp-style message references, never threads.
- 📥 **Inbox & unread tracking** — denormalized counters and activity ordering for fast, N+1-free reads.
- 🗂️ **Per-participant state** — archive, star, block, spam, clear — all participant-specific; the conversation stays neutral.
- 🧹 **Clear without deleting** — a visibility reset; history reappears when a new message arrives.
- 🛡️ **Block / spam** — mutual: while in place neither side can send, history is preserved.
- 🚩 **Message reporting** — report specific messages.
- 📡 **Optional realtime** — event-driven broadcasting (Reverb / Pusher / Echo). Works fully without it.
- 🧩 **Composable send pipeline** — plug in your own validation, filtering and moderation.

## Installation

```bash
composer require syriable/laravel-messenger
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="laravel-messenger-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="laravel-messenger-config"
```

## Setup

Add the `Messageable` trait and `MessengerParticipant` contract to any model that can take part in a conversation:

```php
use Illuminate\Database\Eloquent\Model;
use Syriable\Messenger\Contracts\MessengerParticipant;
use Syriable\Messenger\Support\Messageable;

class User extends Model implements MessengerParticipant
{
    use Messageable;
}
```

Participants are morphable, so different model types can message each other (e.g. a `Buyer` and a `SupportAgent`).

## Usage

### Sending messages

A conversation is created automatically on the first message — conversations are never empty.

```php
use Syriable\Messenger\Facades\Messenger;

// Body only
$message = Messenger::send($alice, $bob, 'Hey Bob!');

// Via the participant model
$alice->sendMessageTo($bob, 'Hey Bob!');

// Attachments only, or body + attachments + a reply reference
$alice->sendMessageTo($bob, [
    'body' => 'Here is the file',
    'attachments' => [$request->file('document')],
    'reply_to' => $previousMessage, // or a message id
]);
```

A valid message must contain a **body, at least one attachment, or both**.

### Reading the inbox & messages

```php
// Inbox, ordered by latest activity (unread never reorders it)
$conversations = $alice->inbox();
$conversations = $alice->inbox(['include_archived' => true, 'starred' => true, 'limit' => 25]);

// Messages, chronological (newest at the bottom), respecting the viewer's cleared history
$conversation = Messenger::between($alice, $bob);
$messages = Messenger::messages($conversation, $alice, ['limit' => 50]);

// Unread totals (denormalized — no message scanning)
$alice->unreadConversationsCount();          // sum of unread messages
Messenger::unreadConversations($alice);       // number of conversations with unread
```

### Conversation state (per participant)

```php
Messenger::archive($conversation, $alice);     // and ->unarchive(...)
Messenger::star($conversation, $alice);        // and ->unstar(...)
Messenger::block($conversation, $alice);       // mutual; ->unblock(...)
Messenger::spam($conversation, $alice);        // mutual; ->unspam(...)
Messenger::clear($conversation, $alice);       // visibility reset, no deletion
Messenger::markAsRead($conversation, $alice);  // opening a conversation reads it
Messenger::markAsUnread($conversation, $alice);// marks only the last received message
```

### Reporting a message

```php
Messenger::report($message, $reporter, reason: 'spam', note: 'Unsolicited link');
```

## Events

Every lifecycle operation dispatches an immutable, past-tense domain event you can listen to:

`MessageSent`, `ConversationCreated`, `ConversationArchived` / `ConversationUnarchived`, `ConversationStarred` / `ConversationUnstarred`, `ConversationBlocked` / `ConversationUnblocked`, `ConversationMarkedAsSpam` / `ConversationUnmarkedAsSpam`, `ConversationCleared`, `ConversationRead`, `ConversationMarkedAsUnread`, `MessageReported`.

## Realtime broadcasting

Broadcasting is optional and **event-driven** — it is never coupled into the actions. Enable it:

```php
// config/messenger.php
'broadcasting' => [
    'enabled' => env('MESSENGER_BROADCASTING_ENABLED', true),
    'channel_prefix' => 'messenger',
    'private' => true,
],
```

When enabled, a `MessageSentBroadcast` is broadcast on `messenger.conversation.{id}` (as `message.sent`). Listen with Laravel Echo:

```js
Echo.private(`messenger.conversation.${conversationId}`)
    .listen('.message.sent', (e) => console.log(e));
```

## Customizing the send pipeline

Messages pass through a composable, configurable pipeline before they are stored. Add your own moderation / filtering pipes:

```php
// config/messenger.php
'pipeline' => [
    \Syriable\Messenger\Pipelines\Send\EnsureParticipantsAreValid::class,
    \Syriable\Messenger\Pipelines\Send\EnsureConversationIsNotBlocked::class,
    \Syriable\Messenger\Pipelines\Send\EnsureMessageHasContent::class,
    \Syriable\Messenger\Pipelines\Send\EnsureAttachmentsAreValid::class,
    \App\Messaging\ProfanityFilter::class, // your own SendPipe
],
```

A pipe implements `Syriable\Messenger\Contracts\SendPipe`:

```php
use Closure;
use Syriable\Messenger\Contracts\SendPipe;
use Syriable\Messenger\Data\PendingMessage;

class ProfanityFilter implements SendPipe
{
    public function handle(PendingMessage $message, Closure $next): PendingMessage
    {
        // inspect / mutate / reject, then:
        return $next($message);
    }
}
```

## Authorization

The package is **not** responsible for business authorization (no policies, roles or ACL). Your application decides who may message whom. The package only enforces internal messaging constraints: blocked / spam conversations, participant membership and message validity.

## Architecture

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the full design: thin models, single-responsibility actions, read-only queries, DTOs, the send pipeline, domain events and the performance / denormalization strategy.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
