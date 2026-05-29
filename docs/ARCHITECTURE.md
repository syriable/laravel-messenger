# Architecture

`syriable/laravel-messenger` is a **headless messaging domain platform**. It models one-to-one conversations the way Messenger / Instagram DMs / WhatsApp do, and deliberately stops at the domain boundary: no UI, controllers, routes, policies, permissions or analytics. The host application owns presentation and authorization.

The design favours **simplicity, readability, performance, explicitness and Laravel-native patterns** over enterprise abstractions. There are intentionally **no repositories, CQRS, event sourcing, command buses, service locators or aggregate roots**.

## Layers

```
src/
├── Actions/      # write-side business logic (one responsibility each)
├── Contracts/    # the few interfaces that matter (MessengerParticipant, SendPipe)
├── Data/         # immutable DTOs (NewMessage, PendingMessage, StoredAttachment)
├── Events/       # immutable, past-tense domain events (+ Broadcast/ projections)
├── Exceptions/   # messaging-domain constraint violations
├── Listeners/    # thin side-effects only (broadcasting)
├── Models/       # thin Eloquent models (relations, casts, light scopes)
├── Pipelines/    # composable send-time validation / moderation pipes
├── Queries/      # read-only, side-effect-free reads
├── Services/     # lightweight services (AttachmentService)
├── Support/      # helpers (Messageable trait, Models resolver, ConversationKey)
├── Messenger.php # the public facade-backed entry point
└── MessengerServiceProvider.php
```

### Models — thin

Models contain only relationships, casts, light scopes and simple accessors. No orchestration or side effects. Every table name and model class is resolved through config (`messenger.tables.*`, `messenger.models.*`) via the `Support\Models` resolver, so host apps can swap tables or subclass models.

### Actions — the write side

Actions are the primary business-logic layer. Each has a single responsibility, dispatches a domain event, and is small and explicit (`SendMessageAction`, `ArchiveConversationAction`, `ClearConversationAction`, …). `SendMessageAction` runs the send pipeline, then persists inside a transaction and updates denormalized projections.

### Queries — the read side

Queries are strictly read-only: they never mutate state or dispatch events (`GetInboxConversationsQuery`, `GetConversationMessagesQuery`, `GetUnreadCountQuery`, `FindConversationBetweenQuery`).

### Pipelines — composable pre-send checks

The send pipeline is an ordered, configurable list of pipes (`messenger.pipeline`) implementing `Contracts\SendPipe`. Validation, blocking/spam enforcement, content and attachment rules live here, and host apps can insert their own moderation pipes without touching the action.

### Events & Listeners

Every lifecycle operation dispatches an immutable, past-tense event. Listeners are reserved for thin side-effects such as broadcasting. Broadcasting is **never** coupled into actions: `BroadcastMessageSent` listens for `MessageSent` and emits the `MessageSentBroadcast` only when `messenger.broadcasting.enabled` is true. Domain events are dispatched after the send transaction commits (via `DB::afterCommit`), so they fire only once the host's enclosing transaction — if any — has also committed.

## Key domain decisions

| Concern | Decision |
| --- | --- |
| Conversation uniqueness | A deterministic, order-independent `key` (`Support\ConversationKey`) is stored uniquely, guaranteeing exactly one conversation per participant pair. |
| Lazy creation | A conversation is created only when the first message is sent — never empty. |
| Participant state | `archived/starred/blocked/spammed/cleared/last_read/unread_count` live on the `Participant` row, not the neutral `Conversation`. |
| Block & spam | Mutual: if either participant's row is blocking/spamming, neither may send. History is preserved. |
| Clear | A `cleared_at` visibility reset — no deletion. Only messages after it are visible to that participant; a newer message resurfaces the conversation. |
| Messages | Immutable in v1 (no edit/delete/unsend). Chronological, newest at the bottom. |
| Replies | A lightweight `reply_to_id` reference, never threads. |
| Attachments | Belong directly to messages (not polymorphic in v1). Self-contained storage; configurable size/mime/count limits enforced by the pipeline. |
| Reports | Message-based via a dedicated `MessageReport`, deduplicated per reporter+message. |

## Performance strategy

Performance is a first-class requirement:

- **ULID** primary keys; **indexed** lookups on `(participant_type, participant_id)`, `(conversation_id, created_at)` and `last_message_at`.
- **Denormalized projections**: `conversations.last_message_id` / `last_message_at` make inbox ordering a single indexed sort with no aggregation; `participants.unread_count` makes unread totals a counter read, not a message scan.
- **No N+1**: the inbox joins the participant row and eager-loads `participants` + `lastMessage`; message reads eager-load `attachments` + `replyTo`.
- **Unread never reorders the inbox** — ordering depends solely on latest activity.
- **Caching** is intentionally left to the host application; it is never the source of truth. The database is always authoritative, and any caching layer should sit in front of the read queries without changing their results.

## Authorization boundary

The package enforces only internal messaging constraints — blocked/spammed conversations, participant membership and message validity. All business authorization (who may message whom, roles, permissions) belongs to the host application.

## Design constraints & trade-offs (v1)

These behaviours are deliberate for v1. They keep the package lightweight, headless and host-controlled. Each can be tightened in the host application, and some may become opt-in features later.

### Message reporting is intentionally unrestricted

`Messenger::report()` records a report from any identity against any message; it does **not** require the reporter to be a participant in that message's conversation. Reporting is treated as a host-application concern (moderation queues, abuse handling), so authorization — including "may this identity report this message?" — belongs to the host. The package only guarantees that a given reporter reports a given message at most once. If you need participant-only reporting, gate the call in your application or add a custom check before invoking `report()`.

### The send pipeline is fully configurable — and that includes the safety pipes

The default `messenger.pipeline` provides the package's core guarantees:

| Pipe | Guarantee |
| --- | --- |
| `EnsureParticipantsAreValid` | Sender and recipient differ; a participant cannot message itself. |
| `EnsureConversationIsNotBlocked` | No send while either side has blocked/spammed (mutual). |
| `EnsureMessageHasContent` | Rejects empty messages (no body and no attachments). |
| `EnsureAttachmentsAreValid` | Enforces attachment count, size and type limits. |
| `EnsureReplyIsValid` | A reply must reference an existing message in the same conversation that is still visible to the sender (created after the sender's `cleared_at`). |

The pipeline is config-driven so hosts can insert their own moderation/filtering pipes. The trade-off: **removing a default pipe removes the guarantee it provides.** If you customise `messenger.pipeline`, keep the pipes whose guarantees you still want — e.g. dropping `EnsureMessageHasContent` will allow empty messages to persist. Add to the list; only remove a default pipe when you deliberately want to drop its check.

### No database-level foreign keys

Migrations link conversations, messages, participants and attachments through **indexed columns without foreign-key constraints**. This is deliberate: participants/senders are morphable (so a single FK can't express them), the schema stays portable across database engines, and the domain never performs true deletes (clearing is a visibility reset). Referential integrity is maintained by the package's actions, which always write related rows inside a single transaction. Applications that want database-enforced cascades can add their own follow-up migration with `foreign()` constraints suited to their stack.

### Realtime broadcasts are lightweight notifications

`MessageSentBroadcast` carries core scalar fields (`id`, `conversation_id`, `sender_type`, `sender_id`, `body`, `reply_to_id`, `created_at`) but **not** attachment metadata. The broadcast is a "a message arrived" signal; clients render attachment-only or mixed messages by loading the message (e.g. via `Messenger::messages()`), keeping the realtime payload small and the database authoritative. If you need attachments inline, broadcast a custom event (or override `broadcastWith()`) that includes them.

### Attachment validation is metadata-based

`EnsureAttachmentsAreValid` checks the client-reported extension and MIME type against the configured allow-lists, plus per-file size and per-message count. It does **not** inspect file contents, sniff true types, or scan for malware/archive abuse. This baseline is suitable for trusted or moderated flows. For untrusted input, add a custom `SendPipe` that performs deep content inspection or virus scanning, or enforce it in host-application policies.

### Unread totals exclude archived but include blocked/spam

Unread totals (`Messenger::unreadCount()` / `unreadConversations()`) exclude **archived** conversations by default, mirroring the default inbox (pass `includeArchived: true` to count them). **Blocked and spam** conversations are *not* excluded: per the v1 spec they remain visible in the inbox (only sending is prevented), so their unread messages still contribute to the totals — keeping the badge consistent with what the user sees in their inbox. If you hide blocked/spam threads in your UI, filter their unread out there too.

### First-message race recovery is bounded

A conversation is created lazily on the first message, guarded by a unique `key`. When two first messages race, the loser catches the unique-constraint violation and retries (bounded: `SendMessageAction::$maxCreateAttempts`, with a short incremental backoff) to attach to the winner's conversation. This resolves normal database visibility latency. Under sustained, pathological contention where the winning transaction is still not visible after all attempts, the violation is rethrown — callers performing first contact at extreme concurrency should be prepared to retry. Raise `$maxCreateAttempts` (subclass the action) if your workload needs more headroom.

