# Team E — Package Integration

> How the new UI/Filament layers attach to the existing headless domain
> **without breaking backward compatibility**. The domain package
> (`syriable/laravel-messenger`) is excellent and intentionally minimal; the
> guiding rule is **extend by composition, never fork**.

## 1. Current package — what we build on

Backend inventory (full detail in the engineering inventory; summary here):

- **Public API** (`Messenger` facade): `send`, `between`, `inbox`, `messages`,
  `unreadCount`, `unreadConversations`, `archive/unarchive`, `star/unstar`,
  `block/unblock`, `spam/unspam`, `clear`, `markAsRead`, `markAsUnread`,
  `report`, `pruneAttachments`.
- **Models** (config-swappable via `messenger.models.*`): `Conversation`,
  `Participant`, `Message`, `MessageAttachment`, `MessageReport`.
- **Queries** (read-side, eager-loaded, no N+1): inbox, messages (keyset
  pagination), unread count, find-between.
- **Actions** (write-side, transactional, event-dispatching).
- **Pipeline** (`messenger.pipeline`): validity, block/spam, content,
  attachments, reply — fully configurable.
- **Events**: `MessageSent`, `ConversationCreated`, all participant-state events,
  `MessageReported`, + `MessageSentBroadcast` (ShouldBroadcast, on
  `messenger.conversation.{id}`, alias `message.sent`).
- **Contracts**: `MessengerParticipant`, `SendPipe`. Trait `Messageable`
  implements participant convenience methods.
- **Config**: tables, models, message/report limits, attachments, pipeline,
  broadcasting (`enabled`, `channel_prefix`, `private`).
- **Performance**: ULIDs, denormalized `last_message_*` / `unread_count`,
  microsecond timestamps, keyset pagination, row-locked unread resets,
  bounded first-message race recovery.

**Conclusion:** ~90% of what the UI needs already exists. The UI consumes the
facade/queries; gaps are *additive*.

## 2. Backward-compatibility contract (non-negotiable)

1. **No breaking changes** to existing public method signatures, events, model
   columns, or config keys. New options are added with safe defaults.
2. **No new required dependency** on the domain package. Livewire/Filament live
   in *separate* packages (`…-ui`, `…-filament`) that depend on the domain — the
   headless install is untouched.
3. **New columns/tables are new migrations** (publishable stubs), never edits to
   existing stubs. Hosts who already migrated are unaffected; new features are
   opt-in by running the new migration.
4. **New config keys** are added with backward-safe defaults; existing keys keep
   their meaning.
5. **New events/broadcasts are additive**; existing events keep their payloads.
6. Everything new respects the existing design rules: config-swappable models,
   no DB foreign keys, microsecond timestamps, transactional actions,
   `ShouldDispatchAfterCommit`.

## 3. Integration architecture

```
host app
  └─ uses ─► syriable/laravel-messenger-ui  (Livewire 4 components, theme, Echo)
                 │ depends on
                 ▼
              syriable/laravel-messenger      (domain — unchanged public API)
  └─ optionally ─► syriable/laravel-messenger-filament (plugin → mounts UI + admin)
```

The UI calls **only** the public surface:
- Reads → `Messenger::inbox/messages/unreadCount/between` (+ new search query).
- Writes → `Messenger::send/star/archive/block/spam/clear/markAsRead/
  markAsUnread/report` (+ new save).
- Realtime → subscribes to the package's `message.sent` broadcast (+ new
  read/typing).

No direct Eloquent/DB access from the UI — this preserves the domain as the
single source of truth and keeps the UI swappable.

## 4. New backend work (additive, in the domain package)

Each item: **why**, **shape**, **BC note**.

### 4.1 Saved messages (bookmarks) — P1
- **Why:** UI "Save" action + "Saved" tab (screenshots 2–3).
- **Shape:** new migration `messenger_saved_messages` (`id` ulid,
  `participant_type/id`, `message_id`, `created_at`; unique
  `(participant_type, participant_id, message_id)`). New `SaveMessageAction` /
  `UnsaveMessageAction`, `GetSavedMessagesQuery`, `Messenger::save()/unsave()/
  saved()`, events `MessageSaved/MessageUnsaved`, model `SavedMessage`
  (config-swappable). Mirror existing patterns exactly.
- **BC:** purely additive; new table + new methods.

### 4.2 Search — P1/P2
- **Why:** inbox search field; in-conversation search (P2).
- **Shape:** `SearchInboxQuery`, `SearchMessagesQuery`. Participant-name search
  needs host knowledge → new contract `ParticipantSearchResolver`
  (`search(string $term): iterable<MessengerParticipant>`) the host binds;
  default no-op resolver. Message-body search via indexed `LIKE`; optional
  fulltext index migration (MySQL/Postgres) shipped as a separate publishable
  stub.
- **BC:** additive queries/contract; optional index migration.

### 4.3 Inbox `unread` + isolatable `spam` scopes — P1
- **Why:** filter dropdown (All/Unread/Starred/Archived/Spam).
- **Shape:** extend `GetInboxConversationsQuery` options with `unread => true`
  (filter `unread_count > 0`) and `only_spam => true`. Add matching `inbox()`
  passthrough.
- **BC:** additive options; defaults unchanged.

### 4.4 Presence — P1
- **Why:** online dots + "Last seen".
- **Shape:** contract `PresenceResolver` (`isOnline($p): bool`,
  `status($p): string`, `lastSeenAt($p): ?CarbonInterface`). Default
  implementation backed by a Reverb/Pusher **presence channel** + optional cache;
  host may bind its own (e.g., reading `users.last_seen_at`). **No messaging-
  table storage.**
- **BC:** new contract + binding; nothing stored in domain tables.

### 4.5 Typing — P1
- **Why:** typing indicator.
- **Shape:** convention only — a client/whisper event `typing` on
  `messenger.conversation.{id}`; a tiny helper to emit/listen. No server
  broadcast class, no DB.
- **BC:** additive; no schema/event changes.

### 4.6 Read-receipt broadcast — P2
- **Why:** live "Read" status for the sender.
- **Shape:** `ConversationReadBroadcast` (ShouldBroadcast) emitted by a listener
  on the existing `ConversationRead` event when broadcasting is enabled; payload
  = `{conversation_id, participant, read_at}`. Mirrors the existing broadcast
  pattern (decoupled via listener, gated by config).
- **BC:** additive listener + event; existing `ConversationRead` unchanged.

### 4.7 Notifications — P2
- **Why:** notify recipients of new messages.
- **Shape:** opt-in `NotifyRecipient` listener on `MessageSent` →
  `NewMessageNotification` (database/broadcast/mail). Per-conversation mute is a
  host preference; provide a `shouldNotify` hook. Disabled by default.
- **BC:** additive, opt-in via config.

### 4.8 Inbox realtime signal — P2
- **Why:** Sidebar updates without polling.
- **Shape:** lightweight broadcast on `messenger.user.{participant}` when a
  conversation's activity/unread changes (driven by `MessageSent`/state events).
  Gated by broadcasting config.
- **BC:** additive; off unless broadcasting enabled.

### 4.9 Participant resolution & name/avatar — P1 (UI need)
- **Why:** UI must render names/avatars/handles; the domain only knows morph
  type+id.
- **Shape:** contract `ParticipantPresenter` (`displayName($p)`, `avatarUrl($p)`,
  `handle($p)`, `profileUrl($p)`, `timezone($p)`) bound by the host; ship a
  sensible default (uses `name`/`avatar` if present). The UI never assumes column
  names.
- **BC:** lives in the UI package; domain unaffected.

> Items 4.4, 4.5, 4.9 are *contracts the host implements* — the package can't
> know host identity/presence details, so we define small interfaces with
> default fallbacks rather than guessing schema.

## 5. Authorization & channels

- The domain stays headless (no policies). Channel auth + UI policies live in the
  **UI/Filament** packages.
- Ship publishable `routes/channels.php` gating `messenger.conversation.{id}`
  (must be one of the two participants), `messenger.user.{id}` (self), and the
  presence channel. Default participant = authenticated user; host can override
  the resolver.
- Filament resources/pages gated by Filament policies (host-defined).

## 6. Database changes summary

| Migration (new stub) | Purpose | Priority |
|----------------------|---------|----------|
| `messenger_saved_messages` | bookmarks | P1 |
| `messenger_messages` **fulltext index** (optional, separate) | body search | P2 |
| (none for presence/typing/read — transport only) | — | — |
| optional `message_reports.resolved_at/resolved_by` (Filament moderation) | report triage status — **in Filament migration**, not domain | P2 |

All additive. Existing five migrations are **never modified**.

## 7. Compatibility test strategy

- Snapshot the current public API (method signatures, event payloads, config
  keys) and assert it in a Pest "architecture/contract" test that fails on
  accidental breakage.
- The new `…-ui`/`…-filament` packages get their own test suites; the domain
  package's existing suite must stay green with zero edits to existing files.
- Run the matrix: Laravel 11/12/13 × PHP 8.3/8.4, with and without broadcasting,
  SQLite + MySQL (row-locking paths).

## 8. Versioning

- Domain package: minor bump (e.g., `1.x → 1.y`) for the additive features
  (saved messages, search, scopes, presence/typing contracts, read/inbox
  broadcasts) — **no major bump needed** since nothing breaks.
- New packages start at `0.x` until the UI stabilises, then `1.0` aligned with
  the domain feature set.
</content>
