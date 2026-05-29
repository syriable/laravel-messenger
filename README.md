<p align="center">
    <img src="art/header.png" alt="Laravel Messenger" width="100%">
</p>

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

Publishing the migrations is **required** — the package ships them as
customisable stubs and does not run them automatically. Publish, then migrate:

```bash
php artisan vendor:publish --tag="messenger-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="messenger-config"
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

// Unread totals (denormalized — no message scanning; archived excluded by default)
$alice->unreadMessagesCount();               // total unread messages
$alice->unreadConversationsCount();          // number of conversations with unread
Messenger::unreadCount($alice);              // total unread messages
Messenger::unreadConversations($alice);      // number of conversations with unread
Messenger::unreadCount($alice, includeArchived: true); // include archived threads
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

Broadcasting is optional and **event-driven** — it is never coupled into the actions. It is **disabled by default**; turn it on by setting `MESSENGER_BROADCASTING_ENABLED=true`. The published configuration defaults:

```php
// config/messenger.php
'broadcasting' => [
    'enabled' => env('MESSENGER_BROADCASTING_ENABLED', false),
    'channel_prefix' => 'messenger',
    'private' => true,
],
```

When enabled, a `MessageSentBroadcast` is broadcast on `messenger.conversation.{id}` (as `message.sent`). Listen with Laravel Echo:

```js
Echo.private(`messenger.conversation.${conversationId}`)
    .listen('.message.sent', (e) => console.log(e));
```

The broadcast is a lightweight notification — it carries the message's core fields but **not** attachment metadata. Clients render attachment-only or mixed messages by loading the message (e.g. `Messenger::messages()`). To include attachments inline, broadcast a custom event or override `broadcastWith()`.

## Customizing the send pipeline

Messages pass through a composable, configurable pipeline before they are stored. Add your own moderation / filtering pipes:

```php
// config/messenger.php
'pipeline' => [
    \Syriable\Messenger\Pipelines\Send\EnsureParticipantsAreValid::class,
    \Syriable\Messenger\Pipelines\Send\EnsureConversationIsNotBlocked::class,
    \Syriable\Messenger\Pipelines\Send\EnsureMessageHasContent::class,
    \Syriable\Messenger\Pipelines\Send\EnsureAttachmentsAreValid::class,
    \Syriable\Messenger\Pipelines\Send\EnsureReplyIsValid::class,
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

> The default pipes provide the package's core guarantees (valid participants, mutual block/spam, non-empty messages, attachment limits, valid replies). The pipeline is yours to customise, but **removing a default pipe removes the guarantee it provides** — e.g. dropping `EnsureMessageHasContent` lets empty messages persist. Add pipes freely; only remove a default one when you intend to drop its check. Note that `EnsureAttachmentsAreValid` validates client-reported type/size/count metadata only — add your own pipe for deep content inspection or virus scanning of untrusted uploads. See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md#design-constraints--trade-offs-v1).

## Authorization

The package is **not** responsible for business authorization (no policies, roles or ACL). Your application decides who may message whom. The package only enforces internal messaging constraints: blocked / spam conversations, participant membership and message validity.

Consistent with this, **message reporting is unrestricted**: `Messenger::report()` accepts a report from any identity against any message and does not require the reporter to be a participant. Gate it in your application if you need participant-only reporting.

## Security notes

Because the package is headless and host-owned, a few responsibilities sit with your application:

- **Attachment access.** `$attachment->url` returns `Storage::disk($disk)->url($path)` with no signing or authorization. If you store attachments on a **public** disk, those URLs are world-readable. Use a private disk and serve files through an authorized controller (or `temporaryUrl()` on a disk that supports it). The package never gates file access for you.
- **Mass assignment.** Package models use `$guarded = []` and are intended to be written **only** through the package's actions (`Messenger::send()`, `report()`, etc.), never filled directly from request input. Do not do `Message::create($request->all())` or `$participant->update($request->all())` — that would let callers tamper with fields like `unread_count`, `blocked_at` or `sender_id`. Treat the models as internal domain objects.
- **Blocked / spam conversations stay in the inbox.** Blocking or marking spam prevents *sending* (mutually) but, per the v1 spec, keeps history visible and stored — so these conversations still appear in `Messenger::inbox()`. Each returned `Conversation` exposes the participant's `blocked_at` / `spammed_at` state for your UI to filter or badge as you see fit.

## Architecture

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the full design: thin models, single-responsibility actions, read-only queries, DTOs, the send pipeline, domain events and the performance / denormalization strategy.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
