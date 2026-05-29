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

Every lifecycle operation dispatches an immutable, past-tense event. Listeners are reserved for thin side-effects (broadcasting, cache invalidation). Broadcasting is **never** coupled into actions: `BroadcastMessageSent` listens for `MessageSent` and emits the `MessageSentBroadcast` only when `messenger.broadcasting.enabled` is true.

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
- **Caching is optional** and only ever a projection optimisation; the database remains the source of truth and the package works fully with caching disabled.

## Authorization boundary

The package enforces only internal messaging constraints — blocked/spammed conversations, participant membership and message validity. All business authorization (who may message whom, roles, permissions) belongs to the host application.
