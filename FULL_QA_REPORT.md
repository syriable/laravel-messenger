# Laravel Messenger — Full End-to-End Human Simulation QA Report

**Package:** `syriable/laravel-messenger`  
**Test App:** Fresh Laravel 13.12 installation at `/tmp/messenger-testapp`  
**Date:** 2026-05-30  
**Method:** Real HTTP application, 5 human-simulated users (Alice, Bob, Charlie, David, Eve), 100-user production dataset, pcntl_fork concurrency tests, performance benchmarking  
**Test App URL:** `http://127.0.0.1:8765`  

---

## Executive Summary

A complete disposable Laravel 13 application was built on top of the package, driven through real HTTP interactions as five different user personas, and stress-tested at multiple concurrency levels. The package installs cleanly, the send path is robust, domain events are well-designed, and the permission model is correctly enforced. However, three production-impacting issues were confirmed through live behavioral observation: a timestamp-precision bug that makes a user's own message invisible after clearing history, a missing morph-map guidance that will cause long VARCHAR keys in production, and an unbounded `messages()` result set. No unauthorized data access was possible.

**Release-readiness score: 6.5 / 10**

---

## Release Recommendation

### RELEASE WITH CAUTION

The package is functional and the core send path is correct. Two confirmed bugs (timestamp precision and unbound reads) will hit real users in production. The installation experience is clean but requires one important note the README does not provide (morph map). The concurrency story is solid *in principle* but untested against a real concurrent database (all CI runs on SQLite).

---

## Installation Findings

### PASS — Installation follows exactly two commands

```bash
composer require syriable/laravel-messenger
php artisan vendor:publish --tag="messenger-migrations"
php artisan migrate
```

No errors. All five tables created. Config published successfully.

### FINDING — Default morph class is the full FQCN

`getMorphClass()` returns `App\Models\User` (the full class name). On a standard Laravel app without a morph map this is stored in the `participant_type` column as the full namespace. If the class is ever renamed or moved, all existing participant rows become orphaned. The README does not mention recommending `Relation::morphMap()` for production.

**Evidence:** `getMorphClass()` output: `App\Models\User`

**Severity:** Medium

### FINDING — Exception hierarchy not documented; HTTP 500 on first wrong file type

A new developer copying the README's send example only encounters `ConversationBlockedException`, `InvalidMessageException`, `InvalidReplyException` in the docs/README. The first time they upload a disallowed file type, they get an **HTTP 500** because `InvalidAttachmentException` is not in the documented catch list. All exceptions extend `MessengerException` but this base class is not mentioned in the README.

**Evidence:** Uploading `test.html` (type `text/html`) to the send endpoint returns HTTP 500 before the catch-base-class fix is applied.

**Severity:** Medium (DX / first-use breakage)

---

## Functional Findings

### ✓ PASS — Conversation creation, messaging, reply-to

- First message creates conversation automatically.
- Reply-to persists and renders correctly.
- Rapid back-and-forth (10 sequential messages) preserved all messages in order.

### ✓ PASS — Archive / Unarchive

- Archived conversations disappear from the default inbox.
- Archived tab (`include_archived: true`) shows them.
- Unarchive restores to inbox immediately.

### ✓ PASS — Star

- Starred conversations appear in the starred filter.

### ✓ PASS — Block mutual enforcement

- After Alice blocks, Alice's send returns 302 → `/inbox` (error path).
- After Alice blocks, Bob's send also returns 302 → `/inbox` (error path).
- DB message count unchanged — neither message stored.
- After unblock, sends succeed.

### ✓ PASS — Spam mutual enforcement

- Same behavior as block. Confirmed Charlie's spam prevents David's send.

### ✓ PASS — Clear (visibility reset)

- Alice clears → sees "No messages visible."
- Bob's view is unaffected.

### ✗ FINDING [CRITICAL] — Clear + immediate send produces invisible message and hidden conversation

**Observed behavior:** When a user clears their history and sends a new message in the same second (the `cleared_at` and new `created_at` share the same 1-second-precision timestamp), the new message is invisible to the sender and the conversation disappears from their inbox.

**Evidence:**
```
cleared_at raw:        '2026-05-30 20:17:46'
msg created_at:        '2026-05-30 20:17:46'
same timestamp:        YES — BUG CONFIRMED
visible to Alice:      0 (expected 1 minimum)
Inbox visible:         NO — conversation also hidden
```

**Root cause:** `GetConversationMessagesQuery` uses `WHERE created_at > cleared_at` (strict `>`). When both share the same second-level timestamp, the message is excluded. Similarly the inbox query uses `WHERE cleared_at < last_message_at` which also excludes the equal case.

**User impact:** The user successfully sends a message, receives no error, but the message is gone from their UI. The conversation disappears from the inbox. This is reproducible on any system where the clear and send happen within the same second — easily triggered on a fast connection or programmatically.

**Severity:** Critical

**Reproduction steps:**
1. Alice clears conversation via any method
2. Alice immediately sends a message
3. Alice's inbox no longer shows the conversation
4. Opening the conversation shows "No messages visible"
5. Bob can see the message normally

### ✓ PASS — markAsRead (auto-read on open)

Confirmed: opening a conversation decrements unread_count to 0 and updates last_read_at.

### ✗ FINDING [Medium] — markAsUnread sets count to 1 regardless of actual received messages

**Evidence (probe):** After 3 unread messages, `markAsUnread` drops the count to 1.

**User impact:** Unread badge says "1" when there are actually 3 unread messages. After manually marking unread then re-reading, the count is permanently wrong until a new message arrives.

### ✓ PASS — Message reporting (updateOrCreate idempotency)

Reporting the same message 5 times kept exactly 1 report record, updated to the latest reason.

### ✓ PASS — Non-participant access blocked at HTTP layer (403)

Eve (non-participant) gets 403 on `GET /conversations/{alice-bob-id}` and on `POST /conversations/{id}/send`.

---

## Concurrency Findings

### Environment note

All concurrency tests use SQLite as the database. SQLite uses file-level locking — concurrent writes will fail with `"database is locked"`. This is expected SQLite behavior and does NOT indicate a package bug, but it reveals the package is never tested against a real concurrent-write database.

### ✗ FINDING [High] — 15 out of 20 parallel sends lost on SQLite ("database is locked")

**Test:** 20 `pcntl_fork()`-spawned workers each calling `Messenger::send()` simultaneously.

**Result:** Before: 20 messages. After: 25 messages. Expected: 40. Lost: 15.

**Error:** `SQLSTATE[HY000]: General error: 5 database is locked`

**Assessment:** This is SQLite-specific. On MySQL/PostgreSQL with row-level locking the package's design (atomic `INCREMENT`, `lockForUpdate` on participants) is sound. However, the package has **no CI against MySQL or PostgreSQL**, so the row-locking path is unverified in real concurrency. The SQLite failures also surface unhandled `QueryException` that propagates through the send pipeline.

**Severity:** High (for production deployments; Medium for SQLite test-only)

### ✓ PASS — No duplicate conversations from first-message race

**Test:** 10 workers simultaneously sending the first message between Eve and Charlie.

**Result:** 1 conversation created (no duplicates). The unique constraint + retry logic prevented race condition. 7 out of 10 messages stored (3 lost to SQLite locking).

### ✓ PASS — Unread counter is atomic and consistent

**Observed:** After workers successfully stored 7 messages, `unread_count = 7` exactly. The `INCREMENT` SQL ensures no double-count or under-count for messages that did persist.

### ✓ PASS — Double-click send stores both messages (no idempotency on package side)

**Observed:** Two rapid sends with the same body using the same CSRF token both return 302 (success) and both messages are stored. The package correctly has no idempotency guard — this is a host/UI responsibility. Both sends created distinct ULID-keyed messages.

**Severity:** Low (expected — host must disable the send button on submit)

---

## Performance Findings

Test environment: SQLite, 100 users, 491 conversations, 5,922 messages.

| Operation | Time | Queries | Notes |
|-----------|------|---------|-------|
| `inbox()` (light user) | 4.4ms | 3 | Fast |
| `inbox()` (many convs) | 1.2ms | 3 | Fast |
| `between()` | 0.6ms | 1 | Fast |
| `messages()` — 1000 msg, no limit | 22.6ms | 3 | Returns 985 (15 hidden by clear) |
| `messages(limit:50)` | 9.4ms | 3 | Fast |
| `send()` — existing conv | 11.7ms | 7 | Reasonable |
| `unreadCount()` | 0.3ms | 1 | Fast |
| Peak memory — 1000-msg load | 28MB | — | Acceptable for PHP |

### ✓ PASS — inbox() is N+1-free

The `inbox()` query is a single JOIN + two eager-load passes. Iterating all returned conversations and accessing `participants` / `lastMessage` causes **zero additional queries**. Excellent.

### ✗ FINDING [Medium] — Accessing participant User models in inbox is host-level N+1

The package eager-loads `Participant` rows but **not** the actual User (or other model) behind each participant. Every inbox item requires a separate `User::find($other->participant_id)` call from the host. Building an inbox for 50 conversations with the pattern `User::find($other->participant_id)` = 50 additional queries.

**No package-provided helper** to eager-load the polymorphic participant in one pass.

**Evidence:** Accessing User model for 5 inbox items generated 4 additional queries (1 per User::find).

**Severity:** Medium (real N+1 in every production inbox implementation)

### ✗ FINDING [Medium] — `messages()` no-limit loads entire conversation history into memory

With a 1000-message conversation, `messages()` returns all 985 visible messages in one call. On a production chat with 10,000+ messages this will exhaust PHP memory. No built-in pagination or keyset cursor is provided.

**Evidence:** 985 Eloquent models loaded; memory used: ~22MB for the full PHP process.

**Severity:** Medium

### ✗ FINDING [Low] — Large file uploads return HTTP 413 (server-level), not package validation

An 11MB file (exceeding the 10MB default) returns HTTP 413 from PHP/nginx before reaching Laravel. The user sees a raw server error, not the package's friendly validation message.

**Severity:** Low (server config dependent)

---

## Security Findings

### ✓ PASS — Non-participant read blocked (403)

`GET /conversations/{id}` returns 403 for non-participants. Verified with Eve vs Alice+Bob conversation.

### ✓ PASS — Non-participant write blocked (403)

`POST /conversations/{id}/send` returns 403 for non-participants. Eve cannot inject into Alice+Bob's conversation.

### ✓ PASS — Non-participant state mutation blocked

`resolveParticipant()` throws `InvalidParticipantException` when a non-participant calls archive/block/etc. Verified: Eve's archive attempt failed with exception; Alice's `archived_at` unchanged.

### ✓ PASS — SQL injection body stored safely (parameterized queries)

`'; DROP TABLE messenger_messages; --` stored verbatim. No injection possible.

### ✓ PASS — XSS body stored verbatim (host must escape on render)

`<script>alert("xss")</script>` stored and returned as-is. The package does not encode. Host templates must escape (Blade's `{{ }}` handles this by default).

### ✗ FINDING [Medium] — Attachment URL is unsigned and world-readable on public disks

`$attachment->url` returns an unsigned path (e.g., `/storage/messenger/attachments/01K…pdf`). On any disk configured as `public`, files are accessible to anyone who knows or guesses the path. ULIDs are not guessable, but there's no access control.

**Evidence:** `url = /storage/messenger/attachments/01KSX8GTZQ2J5TJ9MB38JWQ8FM.pdf`

**Note:** Documented in README. Default disk is `local`. Flagged for completeness.

**Severity:** Medium (on public disk)

### ✗ FINDING [Medium] — Spoofed MIME/extension accepted (PHP-as-PNG)

A file with PHP content named `avatar.png` with `image/png` MIME type is accepted and stored. The package validates the client-reported MIME only.

**Evidence:** `STORED! name=avatar.png mime=image/png` — PHP content on disk.

**Note:** Documented in README. Severity elevated because the default disk is `local` (not `public`), limiting exploitability unless the host misconfigures.

**Severity:** Medium

### ✓ PASS — Zero-byte attachments: stored but not exploitable

Zero-byte files pass validation and are stored with `size=0`. Non-critical; cosmetic issue.

---

## Attachment Findings

| Test | Result |
|------|--------|
| Valid PDF upload (52 bytes) | ✓ Stored, file on disk |
| Disallowed type (.html) | ✓ Rejected (error message surfaced) |
| Spoofed PHP→PNG | ✗ Accepted (known, documented) |
| Zero-byte PDF | ✗ Accepted (undocumented) |
| 11MB ZIP (>10MB limit) | 413 Server error (not package validation) |
| 11 attachments (>10 limit) | ✓ Rejected with InvalidAttachmentException |
| Attachment URL signed | ✗ Unsigned (documented) |
| File exists on disk | ✓ Confirmed via Storage::exists |

---

## Broadcasting Findings

### ✓ PASS — Disabled by default, works without configuration

Broadcasting disabled (default): events fire, no crash, no side effects.

### ✓ PASS — Channel format matches README

When enabled: channel is `private-messenger.conversation.{id}`, event name is `message.sent`. Matches `Echo.private('messenger.conversation.${id}').listen('.message.sent', ...)` from README.

### ✗ FINDING [Low] — Attachment-only broadcast payload gives receiver no useful signal

When a message has an attachment but no body, the broadcast payload is:  
`{body: null, reply_to_id: null, ...}`  
The receiver cannot distinguish an attachment-only message from a null message without fetching the full message. The README acknowledges this and says "clients render attachment-only messages by loading the message." This is documented but is a real UX friction point.

**Severity:** Low (documented limitation)

---

## Documentation Findings

### ✗ FINDING — Exception hierarchy never mentioned; base class (`MessengerException`) undocumented

No part of the README shows catching `MessengerException`. The only catch example in the docs is the pipeline section. New developers hit HTTP 500 on the first `InvalidAttachmentException`.

**Severity:** High (first-use breakage)

### ✗ FINDING — Morph map guidance absent

The README shows `getMorphClass()` returning string identifiers but never warns that without `Relation::morphMap()`, class renames will orphan all participant rows. A single line of guidance and example would prevent this production footgun.

**Severity:** Medium

### ✗ FINDING — `reply_to` documented without clear error conditions

The README shows `'reply_to' => $previousMessage` without noting that (a) a reply on a brand-new conversation always throws and (b) replies to cleared-history messages are rejected.

**Severity:** Low

### ✗ FINDING — Max body length is character-count but column is byte-limited on MySQL

`max_body_length: 20000` counts characters. A 20,000 × 4-byte emoji = 80,000 bytes, exceeding MySQL `text` (65,535 bytes). The README/config comment says "max length of the body" with no byte/char distinction. Already in issue backlog as #47.

**Severity:** High

---

## Developer Experience Findings

### ✗ FINDING [Medium] — No base exception class documented means 500 errors on first invalid attachment

A new developer integrating the package must catch 6 distinct exception types OR discover the undocumented `MessengerException` base class through source reading.

### ✗ FINDING [Medium] — No helper for resolving participant User models in inbox

The inbox eager-loads `Participant` rows but not the linked User models. There is no `withParticipantModels()` or `loadParticipantUsers()` helper. Every host ends up writing N+1 code until they discover morphTo eager-loading manually.

### ✗ FINDING [Low] — No pagination/cursor API for messages

`messages()` returns a plain `Collection`. A chat with 10,000 messages must implement cursor-based pagination from scratch.

### ✓ PASS — Pipeline is genuinely composable

Adding a custom pipe is straightforward. The `SendPipe` contract is clean. The config key is obvious.

### ✓ PASS — Models are swappable via config

All 5 model classes are replaceable via `messenger.models` config. Tested via `Models::` helpers.

---

## UX Findings

### ✗ FINDING [Critical] — User sends message post-clear, message vanishes (same-second bug)

Already documented. From a user's perspective: they typed a message, hit send, got redirected back to the conversation, and the conversation is gone with no error. There is no "something went wrong" message.

### ✗ FINDING [Medium] — Double-click send creates duplicate messages

No duplicate-send prevention at the package level. Two concurrent sends with the same body both store successfully. Host UIs must disable the send button on submit.

### ✗ FINDING [Low] — Blocked conversation still appears in inbox

Blocking a conversation does not hide it from the inbox (documented). In a typical UX a user expects blocking to silence the contact, but they still see the conversation thread with a "blocked" badge. The package behavior is correct per spec but surprises users.

### ✗ FINDING [Low] — Error messages rely on `back()` redirect; with no Referer they go to `/inbox`

When a send fails (blocked, bad attachment, empty body), the controller calls `back()`. Without a `Referer` header (e.g., API clients, Inertia SPAs with redirects stripped), errors route to `/inbox` and are lost. Consider using `redirect()->route('conversation.show', $id)` as the fallback.

---

## Reproduction Steps for Critical/High Issues

### BUG-1: Clear + Immediate Send — Invisible Message (Critical)

```php
$alice = User::find(1); $bob = User::find(2);
$conv = Messenger::between($alice, $bob);
Messenger::clear($conv, $alice);
$msg = Messenger::send($alice, $bob, 'Post-clear message');
echo Messenger::messages($conv->fresh(), $alice)->count(); // 0 — message invisible
```

**Fix:** Change `WHERE created_at > cleared_at` to `WHERE created_at >= cleared_at` in `GetConversationMessagesQuery` and update the inbox visibility check to `<=` respectively.

### BUG-2: Exception Hierarchy (High)

```php
// Produces HTTP 500 — InvalidAttachmentException not caught
$file = $request->file('attachment'); // .html file
Messenger::send($from, $to, ['attachments' => [$file]]);
// Solution: catch MessengerException (undocumented base class)
```

### BUG-3: SQLite Concurrent Writes (High — driver specific)

```
pcntl_fork() x 20 workers → 15 receive "database is locked"
```

**Mitigation:** Document that production deployments require MySQL/PostgreSQL; add a CI pipeline against MySQL.

---

## Summary Table of All Issues

| ID | Issue | Severity | New Issue? |
|----|-------|----------|------------|
| BUG-1 | Clear+send same-second → invisible message & hidden inbox | Critical | New |
| BUG-2 | Exception hierarchy undocumented → HTTP 500 on invalid attachment | High | New |
| PERF-1 | No MySQL CI; concurrent writes untested on real DB | High | New |
| P1 | MySQL body byte overflow (already #47) | High | #47 |
| DX-1 | Inbox: N+1 on participant User model resolution | Medium | New |
| DX-2 | No `messages()` pagination/cursor | Medium | New |
| DX-3 | Morph map guidance absent | Medium | New |
| DX-4 | `markAsUnread` collapses true count to 1 (already #50) | Medium | #50 |
| SEC-1 | Attachment URL unsigned on public disk (already #27) | Medium | #27 |
| SEC-2 | Spoofed MIME/ext accepted (already #51) | Medium | #51 |
| UX-1 | Double-click creates duplicate messages | Low | New |
| UX-2 | Blocked conv still in inbox surprises users | Low | Documented |
| UX-3 | Error path via back() lost without Referer | Low | New |
| BCAST-1 | Attachment-only broadcast has null body (already documented) | Low | Documented |
