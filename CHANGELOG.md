# Changelog

All notable changes to `laravel-messenger` will be documented in this file.

## Unreleased

### Fixed

- Increment the recipient unread counter atomically so concurrent sends cannot lose an update (#4).
- Recover from a lost first-message create race by attaching to the winning conversation instead of failing with a unique-constraint error (#3).
- Remove stored attachment files when the send transaction rolls back, so a failed send leaves no orphaned files (#7).
- Dispatch `MessageSent` and `ConversationCreated` only after the transaction commits, so listeners and broadcasts observe persisted data (#8).
- Validate reply references: a reply must point to an existing message in the same conversation, otherwise the send is rejected (#5).
- Reject replies to messages the sender has cleared from their own view (#25).
- Re-check block/spam state inside the send transaction (with the participant rows locked) so it cannot be bypassed in the time-of-check/time-of-use gap (#28).
- Retry the first-message creation race with bounded attempts and a short backoff, covering the window before the winning transaction is visible (#26).
- Fail the send when a participant row is missing instead of silently skipping projection updates (#29).
- Reject attachments whose size cannot be determined (`getSize()` returning `false`) (#22).
- Treat a failed `storeAs()` as a hard error so the send rolls back and stored files are cleaned up (#21).
- Enforce a configurable maximum message body length (#19) and report reason/note lengths (#20).
- Exclude archived conversations from unread totals by default (with an `includeArchived` option) (#17).
- Correct the README broadcasting default to match the published config (`false`) and remove a duplicate heading (#10).

### Changed

- Dispatch `MessageSent` and `ConversationCreated` via `DB::afterCommit`, so they fire only after the host's enclosing transaction commits, not just the package's inner one (#8, #23).
- Add `Messageable::unreadMessagesCount()` and fix `unreadConversationsCount()` to return the conversation count its name implies (#30).
- Remove the unused `cache` configuration block (caching was never wired into the read paths) (#11).
- Remove the dead `Workbench\App\` dev autoload mapping (no `workbench/` directory exists) (#31).

### Documentation

- Document the intentional v1 design constraints and trade-offs: unrestricted message reporting (#6), required send-pipeline pipes (#9), absence of database foreign keys (#12), the lightweight realtime broadcast payload (#13) and metadata-based attachment validation (#14).
- Add a Security notes section covering attachment URL exposure on public disks (#27), mass-assignment expectations for package models (#32) and blocked/spam inbox visibility (#18); align the architecture doc with the removed cache config (#24).

## 0.9.0 - 2026-05-29

First public, feature-complete release of **Laravel Messenger** — a headless, backend-only one-to-one messaging domain platform for Laravel (Messenger / Instagram DMs / WhatsApp-style). Released under `0.x` so the schema and public API can be refined from real-world feedback before a stable `1.0.0`.

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

**Requires** PHP 8.3+ · Laravel 12–13. See the [README](https://github.com/syriable/laravel-messenger#readme) and [`docs/ARCHITECTURE.md`](https://github.com/syriable/laravel-messenger/blob/main/docs/ARCHITECTURE.md).
