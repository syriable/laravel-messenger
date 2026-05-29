# Changelog

All notable changes to `laravel-messenger` will be documented in this file.

## 0.9.0 - 2026-05-29

First public, feature-complete release of the headless one-to-one messaging domain platform. Released under `0.x` to allow the schema and public API to be refined based on real-world usage before committing to a stable `1.0.0`.

### Added

- One-to-one conversations with a deterministic uniqueness key and lazy creation on the first message.
- Morphable participants via the `MessengerParticipant` contract and `Messageable` trait.
- `SendMessageAction` with a composable, configurable send pipeline (participant, block/spam, content and attachment validation).
- Self-contained attachment storage lifecycle (`AttachmentService`) with configurable size/mime/count limits.
- Per-participant conversation state: archive, star, block (mutual), spam (mutual), clear (visibility reset), read/unread.
- Lightweight WhatsApp-style message replies.
- Message reporting via a dedicated `MessageReport` model.
- Read-only queries: inbox (activity-ordered, N+1-free), conversation messages (clear-aware), unread totals (denormalized counters).
- Immutable, past-tense domain events for every lifecycle operation.
- Optional, event-driven realtime broadcasting (`MessageSentBroadcast` + `BroadcastMessageSent` listener).
- ULID primary keys, indexed lookups and denormalized projections for performance.
