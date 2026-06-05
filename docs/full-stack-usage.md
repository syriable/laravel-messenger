# Full-Stack UI — Livewire 4 + Filament

The package ships a headless messaging domain **plus** an optional, batteries-
included front end: a **Livewire 4** chat interface and a **Filament 5**
moderation plugin. Both are *opt-in* — installing them changes nothing for
headless consumers, and the domain has no hard dependency on either.

- New here? Start with the [README](../README.md) for the domain (sending,
  inbox, conversation state). This document covers the UI, the Filament
  integration, and the additive features that back them (saved messages, search,
  reactions, presence, read receipts).

## Contents

1. [Requirements & install](#requirements--install)
2. [Mounting the chat UI](#mounting-the-chat-ui)
3. [Identity, presence & search resolvers](#identity-presence--search-resolvers)
4. [Theming](#theming)
5. [Realtime (Echo / Reverb) and the polling fallback](#realtime-echo--reverb-and-the-polling-fallback)
6. [Internationalisation & RTL](#internationalisation--rtl)
7. [Additive domain features](#additive-domain-features)
8. [Filament moderation plugin](#filament-moderation-plugin)
9. [Configuration reference](#configuration-reference-ui)

---

## Requirements & install

| Layer | Requires |
| --- | --- |
| Domain (always) | PHP 8.3+, Laravel 11/12/13 |
| Chat UI | `livewire/livewire ^4.0` |
| Realtime (optional) | `laravel/echo` + a broadcaster (`laravel/reverb` or Pusher) |
| Moderation | `filament/filament ^5.0` |

```bash
composer require syriable/laravel-messenger
composer require livewire/livewire        # for the chat UI

# publish & run migrations (includes saved-messages and reactions tables)
php artisan vendor:publish --tag="messenger-migrations"
php artisan migrate

# publish config, the compiled stylesheet, and (optionally) translations
php artisan vendor:publish --tag="messenger-config"
php artisan vendor:publish --tag="messenger-assets"          # -> public/vendor/messenger/messenger.css
php artisan vendor:publish --tag="messenger-translations"    # optional
```

> The UI relies on the same publishable migrations as the domain. If you adopted
> the package before saved messages / reactions existed, just re-publish — the
> new stubs (`0006_*`, `0007_*`) are additive and never modify existing tables.

## Mounting the chat UI

Two ways to render the messenger:

**1. The bundled route (fastest).** Enabled by default at `/messages`:

```php
// config/messenger.php
'ui' => [
    'enabled'  => true,
    'route'    => ['prefix' => 'messages', 'name' => 'messenger.', 'middleware' => ['web', 'auth']],
],
```

Visit `/messages`. A conversation is deep-linkable via `?c={conversationId}`.

**2. Embed the component** in your own Blade/layout (set `ui.enabled => false`
to skip the bundled route):

```blade
<x-app-layout>
    <div style="height: 80vh">
        <livewire:messenger />
    </div>
</x-app-layout>
```

Make sure the published stylesheet is loaded:

```blade
<link rel="stylesheet" href="{{ asset('vendor/messenger/messenger.css') }}">
```

The interface assumes the **authenticated user is the participant**. To change
that (impersonation, multi-guard, tenant scoping) bind your own
`CurrentParticipantResolver` — see below.

## Identity, presence & search resolvers

The domain stores only a participant's morph type + key, so the UI resolves
display details through small, swappable contracts. Each has a safe default;
override via config or a container binding.

| Contract | Default | Purpose |
| --- | --- | --- |
| `ParticipantPresenter` | reads `name`/`avatar_url`/`username`/… or `messenger*()` methods | names, avatars, handles, timezone |
| `CurrentParticipantResolver` | the authenticated user | "who am I" |
| `PresenceResolver` | everyone offline | online status + "last seen" |
| `ParticipantSearchResolver` | matches nothing | search participants by name |

```php
// config/messenger.php
'ui' => [
    'participant_resolver' => App\Messenger\CurrentUser::class,
    'presence_resolver'    => App\Messenger\RedisPresence::class,
    'search_resolver'      => App\Messenger\UserSearch::class,
],
'presenter' => App\Messenger\UserPresenter::class,
```

Example presenter and search resolver:

```php
use Syriable\Messenger\Contracts\ParticipantPresenter;
use Syriable\Messenger\Contracts\MessengerParticipant;

class UserPresenter implements ParticipantPresenter
{
    public function displayName(MessengerParticipant $p): string { return $p->name; }
    public function avatarUrl(MessengerParticipant $p): ?string { return $p->avatar_url; }
    public function handle(MessengerParticipant $p): ?string { return $p->username; }
    public function profileUrl(MessengerParticipant $p): ?string { return route('users.show', $p); }
    public function timezone(MessengerParticipant $p): ?string { return $p->timezone; }
}

use Syriable\Messenger\Contracts\ParticipantSearchResolver;
use Illuminate\Support\Collection;

class UserSearch implements ParticipantSearchResolver
{
    public function search(string $term): Collection
    {
        return User::query()->where('name', 'like', "%{$term}%")->limit(50)->get();
    }
}
```

> The default presenter already works if your model exposes `name` / `avatar_url`
> / `username`, **or** opt-in methods `messengerDisplayName()`,
> `messengerAvatarUrl()`, `messengerHandle()`, `messengerProfileUrl()`,
> `messengerTimezone()`. Inbox search matches **message bodies** out of the box;
> bind a `ParticipantSearchResolver` to also match participant names.

## Theming

All visuals are driven by `--msgr-*` CSS custom properties — override a handful
to reskin, no build step:

```css
:root {
    --msgr-accent: #2563eb;          /* brand colour */
    --msgr-rail-width: 360px;        /* conversation rail */
    --msgr-radius: 10px;
}
.dark { --msgr-surface: #0b0b0c; }   /* dark mode responds to .dark */
```

Two message styles ship in: **flat** (email-like, default) and **bubble**
(aligned chat bubbles). Switch per-instance or via config:

```php
'ui' => ['theme' => 'neutral', 'message_style' => 'bubble'],
```

A Tailwind preset (`vendor/syriable/laravel-messenger/tailwind.preset.js`)
exposes the tokens as utilities if you want to compose with your own classes.

## Realtime (Echo / Reverb) and the polling fallback

The UI works **with or without** realtime. When broadcasting is disabled (the
default) the inbox and thread poll on a `.visible` interval. Enable realtime for
live messages, read receipts and typing:

```php
// config/messenger.php
'broadcasting' => ['enabled' => true, 'channel_prefix' => 'messenger', 'private' => true],
```

1. Install a broadcaster (e.g. Reverb) and configure Laravel Echo as usual.
2. Publish and register the channel authorization:

   ```bash
   php artisan vendor:publish --tag="messenger-channels"   # -> routes/messenger-channels.php
   ```

   ```php
   // routes/channels.php
   require base_path('routes/messenger-channels.php');
   ```

   The published routes gate `messenger.conversation.{id}` to its two
   participants, `messenger.user.{type}.{id}` to that user, and a presence
   channel. The default maps participants onto the authenticated user — adjust
   the closures if that isn't true for you.

With `window.Echo` present, the thread subscribes to new messages
(`.message.sent`), read receipts (`.conversation.read`) and typing whispers, and
**polling automatically switches off**. Attachments aren't in the broadcast
payload (the DB stays authoritative) — the client reloads the message to render
them.

Polling intervals are configurable:

```php
'ui' => ['polling' => ['inbox' => '15s', 'thread' => '5s']],
```

## Internationalisation & RTL

All UI strings live under the `messenger::ui.*` namespace and ship in **English
and Arabic**. Publish (`messenger-translations`) to customise or add locales.
The layout uses logical CSS properties, so setting `dir="rtl"` (or using an RTL
locale like `ar`) mirrors the whole interface automatically.

## Additive domain features

These power UI affordances but are usable headlessly via the `Messenger` facade
too. All are per-participant, additive and backward-compatible.

### Saved messages (bookmarks)

```php
Messenger::save($message, $user);                 // bookmark (idempotent)
Messenger::unsave($message, $user);               // remove
Messenger::isSaved($message, $user);              // bool
Messenger::saved($user, ['conversation_id' => $c->id, 'limit' => 50]); // newest first
```

Surfaced in the thread's **Saved** tab and the per-message *Save* action.

### Inbox search

```php
Messenger::searchInbox($user, 'invoice', ['include_archived' => true]);
```

Matches message bodies (case-insensitive) and — with a bound
`ParticipantSearchResolver` — participant names. Honours the clear boundary.

### Reactions

```php
Messenger::react($message, $user, '👍');          // toggle on/off, returns ?MessageReaction
Messenger::reactionsFor([$m1->id, $m2->id], $user); // [message_id => [{emoji,count,reacted}]]
```

The allowed emoji set (also used by the picker) is configurable:

```php
'reactions' => ['allowed' => ['👍', '❤️', '😂', '😮', '😢', '🙏']],
```

### Read receipts & presence

Read status (Sent / Read) is derived from the recipient's `last_read_at` — no
new state. Presence (online / last-seen) comes from your `PresenceResolver`.

### Notifications (opt-in)

Off by default. Enable to notify the recipient of each new message:

```php
'notifications' => ['enabled' => true, 'channels' => ['database', 'mail']],
```

A `NotifyRecipient` listener sends `NewMessageNotification` to the recipient
(any Notifiable model). Muting is host-owned: a recipient may opt out per message
by implementing `shouldReceiveMessengerNotification(Message $message): bool`.
Extend the notification/listener for richer mail formatting or queueing.

### New events

`MessageSaved` / `MessageUnsaved`, `MessageReacted` / `MessageUnreacted`, and the
`ConversationReadBroadcast` projection — all additive and dispatched after
commit.

## Filament moderation plugin

Add reported-message moderation to any Filament 5 panel:

```bash
composer require filament/filament
```

```php
use Syriable\Messenger\Filament\MessengerPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugin(MessengerPlugin::make());
}
```

This registers, under a **Messaging** navigation group:

- **`MessageReportResource`** — a reported-messages queue (reporter, message
  excerpt, reason, reported-at). Row actions are thin wrappers over the domain:
  **Block conversation**, **Mark as spam** (both mutual, on behalf of a
  participant) and **Dismiss** (delete the report).
- **`ChatPage`** — mounts the full `<livewire:messenger />` UI inside the panel
  at `/messages`.

No business logic lives in Filament; it calls `Messenger::*`. Gate the resource
with your own Filament policies as needed.

## Configuration reference (UI)

```php
'presenter' => DefaultParticipantPresenter::class,

'ui' => [
    'enabled'              => true,
    'guard'                => null,
    'participant_resolver' => AuthParticipantResolver::class,
    'presence_resolver'    => NullPresenceResolver::class,
    'search_resolver'      => NullParticipantSearchResolver::class,
    'route'                => ['prefix' => 'messages', 'name' => 'messenger.', 'middleware' => ['web', 'auth']],
    'theme'                => 'neutral',
    'message_style'        => 'flat',   // flat | bubble
    'enter_to_send'        => true,     // Enter sends (Shift+Enter newline) — user-toggleable
    'per_page'             => 30,       // messages per infinite-scroll page
    'polling'              => ['inbox' => '15s', 'thread' => '5s'],
],

'reactions' => ['allowed' => ['👍', '❤️', '😂', '😮', '😢', '🙏']],
```

