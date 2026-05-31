# Changelog

All notable changes to `laravel-messenger` will be documented in this file.

## Unreleased

## [0.10.0] - 2026-05-31

### Fixed

- Retry the send write transaction a bounded number of times on transient concurrency errors (deadlock, lock-wait timeout, SQLite `database is locked`), so a contended send into an existing conversation is no longer silently dropped. Combined with the MySQL/PostgreSQL CI, this verifies that concurrent sends don't lose messages on production drivers; SQLite remains single-writer and should use WAL + a busy timeout, or be swapped for MySQL/PostgreSQL under heavy parallel writes (#76).
- Include every documented option in the published config stub — `validation.verify_participants_exist`, `reports.participants_only` and `attachments.allow_empty` were read with defaults but missing from `config/messenger.php`, so `vendor:publish` now produces a complete reference (#77).
- Store messaging timestamps with microsecond precision (`timestamp(6)` columns + a `HasPreciseTimestamps` model trait) and compare clear/reply/inbox visibility boundaries at microsecond resolution, so a message sent in the same wall-clock second as a clear is no longer wrongly hidden (#63, #67).
- Widen the `body` column to `mediumText` so a message at the configured `max_body_length` always persists losslessly on MySQL/MariaDB, even with multibyte characters (#47).
- `markAsUnread` is now a no-op when the participant has no visible received message (sender-only or cleared history), so the unread badge can never contradict the visible timeline (#50); it continues to mark a single message (#56).
- Make the `markAsRead`/`clear` unread reset concurrency-safe via a locked transaction, so a concurrent inbound increment is no longer silently overwritten (#60).
- Clamp query `limit` options to a minimum of 1 so a zero/negative limit can no longer disable the `LIMIT` clause and return the entire result set (#53).
- Truncate over-length attachment filenames (preserving the extension) so they always fit the `name` column on strict drivers (#52).
- Reject empty (zero-byte) attachment uploads by default (`messenger.attachments.allow_empty`) (#55).
- Make `ConversationKey` collision-proof via length-prefixed segment encoding, so custom morph aliases/keys containing `#` or `|` cannot collide (#58).

### Added

- `exclude_blocked` / `exclude_spam` inbox options to drop conversations the viewer has blocked or marked as spam (kept visible by default per the v1 spec) (#82).
- A metadata-only attachment summary (`has_attachments` + an `attachments` array of `{ id, name, mime_type, size }`) in the `MessageSentBroadcast` payload, so realtime clients can render attachment-only / mixed messages without a follow-up request. No file contents or URLs are broadcast (#81).
- Keyset cursor pagination on `Messenger::messages()` via the mutually-exclusive `before_id` / `after_id` options, so large conversations can load a page at a time (scroll-up / load-newer) instead of hydrating the entire history. Cursors compare on the `(created_at, id)` tuple, exclude the cursor message, and always return chronological order (#71).
- `with_participant_models` inbox option to eager-load the polymorphic model behind each participant (e.g. the `User`) in one grouped query, eliminating the host-side N+1 when rendering inbox names/avatars (#70).
- Run the test suite against MySQL 8 and PostgreSQL 16 in CI through a driver-agnostic test harness (`DB_CONNECTION`), so the atomic-increment, `lockForUpdate` and unique-constraint concurrency paths are verified on real databases, not only SQLite (#69).
- `messenger:prune` command (and `Messenger::pruneAttachments()`) to garbage-collect orphaned attachment files left on disk after host-driven message/conversation deletion. Supports `--dry-run` and `--disk` (#49).
- Optional `messenger.validation.verify_participants_exist` guard: reject sends to a non-existent sender/recipient ("ghost" participant). Off by default (#54).
- Optional `messenger.reports.participants_only` guard: restrict message reporting to conversation participants. Off by default (#57).

### Tests

- Add cross-model (User ↔ Agent) integration coverage, an in-process concurrency stress test, and standalone consuming-app / multi-process QA harnesses under `scripts/` (documented in `CONTRIBUTING.md`) to guard morph messaging, the broadcast contract, spam flows and the first-message race against regression (#64).
- Add keyset cursor pagination guard tests: unbounded cursor (no `limit`) returns the full visible range; a non-positive `limit` clamps to 1 instead of leaking the entire result set; `before_id` / `after_id` pointing at pre-clear messages correctly hide invisible history and surface only post-clear messages (#85, #87).

### Documentation

- Add a "Handling domain exceptions in the host application" README section documenting the `MessengerException` base class and every subclass with suggested HTTP/UX mappings, so first-time integrators no longer hit an uncaught 500 on the first invalid upload (#66, #68).
- Recommend an explicit named-route redirect over `back()` for send-failure handling, since `back()` silently drops the error when no `Referer` header is present (API/Inertia/Livewire) (#74).
- Note that duplicate-submission idempotency is a host responsibility — the package has no de-duplication guard by design (#73).
- Recommend registering a `Relation::morphMap()` before the first migration so participant identity stays portable across class renames, with a warning about orphaned `participant_type` rows otherwise (#72).
- Add a "Database & concurrency" README section documenting the send path's parallel-write guarantees and the bounded concurrency retry, and recommending MySQL/PostgreSQL (or SQLite WAL + busy timeout) for heavy write load (#76).
- Document that conversation-scoped reads (`Messenger::messages()`) and participant-state actions throw `InvalidParticipantException` for non-participants rather than returning empty (#78).
- Stress in the README and the published config that ghost (non-existent) participants are the host's responsibility unless `validation.verify_participants_exist` is enabled (#79).
- Add a security comment on `attachments.disk` in the published config warning that `$attachment->url` is unsigned and that sensitive files need a private disk + authorized route or `temporaryUrl()` (#80).
- Document that duplicate-submission idempotency is a host responsibility — no de-duplication guard by design (#73, #83).
- Document the reply-target validity/visibility constraint near the README `reply_to` example (#59); the lack of cascading deletes and host-owned participant cleanup with nullable `morphTo` accessors (#48, #49); the single-message `markAsUnread` semantic; and the attachment metadata-validation limitation (#51).
- Warn in the `Messenger::messages()` usage example that omitting `limit` loads the entire visible history into memory; always pass a page size when paginating (#87).
- Clarify that `markAsUnread()` sets `unread_count` to 1, not the participant's true historical unread count (#87).
- Add a `Broadcast::channel()` authorization example and a callout that `'private' => false` (the default) means the channel is public and all payload fields are world-readable; recommend switching to `true` for private channels (#87).
- Fix the published config pipeline example to include `EnsureParticipantsExist::class`, which was referenced in configuration documentation but absent from the example (#87).

### Fixed (earlier)

- Correct the documented `vendor:publish` tags to `messenger-migrations` and `messenger-config` (Spatie derives them from the package short name), and state that publishing the migrations is required (#43).
- Stop calling `runsMigrations()`: migrations ship as `.php.stub` files that Laravel's migrator cannot execute, so it gave a false impression of auto-migration. The publish-then-migrate workflow is now the documented path (#44).
- Dispatch **all** domain events (participant-state and reporting events, not just send events) after the enclosing transaction commits, via `ShouldDispatchAfterCommit` on the event classes (#35).
- Re-validate reply visibility inside the write transaction so a clear between the pipeline and persist still rejects the reply (#37).
- Remove the dead `Syriable\Messenger\Database\Factories\` → `database/factories/` autoload mapping (no such directory) (#38).
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
- Document that unread totals exclude archived but intentionally still count blocked/spam threads (#36), the bounded first-message race recovery and its residual failure mode (#39), and the clear-aware reply guarantee in the pipe table (#41).

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
