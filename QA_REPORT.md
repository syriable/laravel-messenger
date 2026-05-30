# Laravel Messenger — Real-World QA & Exploratory Test Report

**Package:** `syriable/laravel-messenger` (headless one-to-one messaging domain)
**Date:** 2026-05-30
**Reviewer role:** Senior QA / Security / Integration / End-User simulation
**Method:** Fresh `composer install`, full existing suite run (179 passing, 367
assertions), plus 14 adversarial runtime probes executed against an in-memory
SQLite app, source audit of every action / pipe / query / model / migration, and
MySQL-portability reasoning. All "evidence" lines below were produced by live
probe runs.

---

## Executive Summary

This is a **genuinely well-engineered package**. The architecture (thin models,
single-responsibility actions, read-only queries, DTOs, a composable send
pipeline, past-tense domain events) is clean and the concurrency story on the
**send path is unusually mature** for a Laravel package: attachments are written
before the DB transaction and cleaned up on rollback, the lazy-conversation
creation race is recovered via a unique key + bounded retry, block/spam is
re-checked under `lockForUpdate` inside the write transaction, the unread counter
is incremented atomically in SQL, and every domain event implements
`ShouldDispatchAfterCommit`. The existing test suite is broad and green.

However, "commercially releasable to thousands of production apps" is a higher
bar, and the probing surfaced a cluster of **real defects and footguns** that sit
*outside* the well-guarded send path: a MySQL data-loss/length mismatch on the
message body, completely **absent referential integrity** (no foreign keys, no
attachment GC), **unvalidated/unbounded read limits**, and **inconsistent
`markAsUnread` state**. Several security properties are correctly *documented* as
host responsibilities, but at least one (mass-assignment via `$guarded = []`)
is a sharp footgun.

**Release-readiness score: 6.5 / 10** — strong core, but ships with a
MySQL-portability bug and integrity gaps that will bite real deployments.

**Release decision: RELEASE WITH CAUTION.**

---

## Critical Issues

None that corrupt the core send path on the tested driver. The most
production-dangerous item is P1 below (MySQL body truncation), which I rate
**High** rather than Critical only because it requires multibyte-heavy bodies and
a default config.

---

## High Severity Issues

### P1 — `body` column (`text`) can be overflowed within the allowed character limit (MySQL)
The body length guard counts **characters** (`mb_strlen`) against
`max_body_length` (default **20,000**), but the column is `text`, capped at
**65,535 bytes** on MySQL.

> **Evidence (probe 8):** a 20,000 × 4-byte emoji body is accepted by the
> pipeline — `char_len = 20000`, `byte_len = 80000`. 80,000 > 65,535.

On MySQL this throws *"Data too long for column 'body'"* (strict mode) or
**silently truncates** (non-strict) — i.e. data loss inside the documented,
allowed limit. SQLite (the test driver) has no such cap, so the suite never sees
it. **Fix:** use `mediumText`/`longText`, or make the guard byte-aware
(`strlen`), and align the default with the column.

### D1 — No foreign keys / no cascade anywhere; orphaned records by design
None of the five migrations declare a foreign key or cascade rule. Deleting a
host participant (a normal account-deletion flow) leaves dangling rows.

> **Evidence (probe 11):** after `$user->delete()`, the message still exists and
> `$message->sender()->first()` returns **NULL**. The conversation, its
> participant rows, attachments and reports all remain.

Inbox/message queries will still surface these threads with a `null`
counterpart, inviting null-pointer crashes in host UIs, and there is no
package-provided way to find or prune them. The morphable design explains the
absence of DB-level FKs, but the package provides **no integrity tooling**
(events on participant deletion, a prune command, nullable-safe accessors) to
compensate.

---

## Medium Severity Issues

### D2 — Attachment files are never garbage-collected
Messages are immutable and have no delete API; `clear` is only a visibility
reset. Attachment files are removed **only** on send-transaction rollback. There
is no prune/GC command, and any host-level deletion of a conversation/message
leaks the underlying files on disk **forever** → unbounded storage growth with
no provided remediation.

### F1 — `markAsUnread` produces incoherent state (phantom unread)
`MarkConversationAsUnreadAction` blindly sets `unread_count = 1`,
`last_read_at = null` with **no check that the participant ever received a
message**.

> **Evidence (probe 1):** the *sender* marks their own side unread →
> `unread_count = 1` for a conversation in which they received nothing.
> **Evidence (probe 3):** `clear()` then `markAsUnread()` →
> `unread_count = 1` while `messages()` returns **0** visible messages.

Result: an unread badge a user can never clear by reading (there is nothing to
read), and a counter that contradicts visible content.

### P1-adjacent / P2 — Attachment original filename can exceed its column
`name` is `string` (255) but oversized client filenames are accepted and stored
unvalidated.

> **Evidence (probe 13):** a 304-character original filename is stored intact on
> SQLite. On MySQL this truncates or throws.

### S4 — `$guarded = []` on every model is a mass-assignment footgun
All package models use `protected $guarded = []`. The README documents that they
must only be written through actions, but nothing enforces it. A host that does
the very common `Message::create($request->all())` or
`$participant->update($request->all())` can set `sender_id`, `unread_count`,
`blocked_at`, `cleared_at`, timestamps, etc. Consider `$guarded`/`$fillable`
hardening or at least `Model::preventSilentlyDiscardingAttributes`-style guidance
beyond a prose note.

### S1 / S2 — Attachment trust model (documented, but real)
- **S1:** Attachment validation trusts the **client-supplied** MIME type and
  extension; no content inspection.
  > **Evidence (probe 5):** a file whose real content is
  > `<?php system($_GET['c']); ?>` but is named `avatar.png` is accepted and
  > stored.
- **S2:** `$attachment->url` returns an **unsigned** `Storage::url()`.
  > **Evidence (probe 14):** `url = /storage/messenger/attachments/01K…​.pdf` —
  > world-readable if the disk is public.

Both are explicitly called out in the README as host responsibilities, which is
defensible for a headless package — but the **default disk is `local`** and the
combination (trusted metadata + unsigned URL + a host that switches to a public
disk) is a realistic RCE/IDOR path. Recommend shipping a louder warning and a
sample authorized-download controller.

---

## Low Severity Issues

### F3 — Read limits are unvalidated; negative limit silently returns everything
`messages()` and `inbox()` pass `(int) $limit` straight to the query builder.
Laravel's `limit()` **ignores negative values** (`if ($value >= 0)`), so a
negative limit applies **no LIMIT at all**.

> **Evidence (probes 6/7 + SQL capture):** `['limit' => -5]` produces a messages
> query with **no `limit` clause** and an inbox query with **no `limit` clause**
> — the full unbounded result set. Meanwhile `limit => 0` returns *nothing*.
> Inconsistent and unvalidated; a DoS/perf footgun on large threads.

### PF3 — `messages()` has no default cap / pagination
With no `limit`, `messages()` loads the **entire** conversation history into
memory. There is no cursor/keyset pagination helper. Combined with F3, large
conversations are a memory risk.

### F2 — `markAsUnread` collapses the true unread count
> **Evidence (probe 2):** 3 unread → `markAsUnread` → `unread_count = 1`.

Documented as "only the last received message," but it means the denormalized
counter stops reflecting reality after a manual unread.

### S5 — No recipient existence validation (ghost conversations)
> **Evidence (probe 12):** `Messenger::send($a, $ghost)` where `$ghost` was never
> persisted **succeeds**, creating a conversation + participant row pointing at a
> non-existent user.

### S3 — Reporting is unrestricted
> **Evidence (probes 9/10):** a non-participant ("Eve") and the message's own
> author can both create reports.

Documented as intentional ("gate it in your application"), noted for awareness —
enables report-spam if the host forgets to gate it.

### P3 — Zero-byte attachments accepted
> **Evidence (probe 4):** an `empty.pdf` of size 0 is stored (`size = 0`).

### C2 — Read/clear vs. inbound-increment race
`markAsRead`/`clear` set `unread_count = 0` via a blind `forceFill` write, while
a concurrent inbound message does an **atomic** `increment`. The atomic increment
can be clobbered by the blind reset (read-wins), losing a just-arrived message's
unread. Small window, low likelihood; worth a note since the send path otherwise
goes to great lengths for atomicity.

### C3 — First-message creation race is bounded to 3 retries
Under sustained contention on the *very first* message between two parties, the
bounded retry can still surface a `UniqueConstraintViolationException` to the
caller.

### Edge — `ConversationKey` separator collision
Keys are `morphClass#id` joined by `|`. A custom morph-map alias or primary key
containing `#` or `|` could theoretically collide two distinct pairs into one
key. Extremely unlikely with default morph classes/ULIDs; flagged for
completeness.

---

## Security Findings (summary)

| ID | Finding | Status |
|----|---------|--------|
| S1 | Attachment type/size validated from **client** metadata only; content not inspected | Documented; default disk `local` |
| S2 | `attachment->url` unsigned / world-readable on public disks | Documented |
| S4 | `$guarded = []` mass-assignment footgun | Documented in prose only |
| S5 | No recipient existence check → ghost/orphan conversations | Not documented |
| S3 | Unrestricted message reporting (any identity, own messages) | Documented as intentional |

No SQL injection, XSS-in-storage, or auth-bypass *within the package's
responsibility* was found — the package is correctly headless and parameterizes
all queries. Body content (HTML/JS/SQL-like) is stored verbatim and **must** be
escaped by the host on render (expected for a backend package).

---

## Performance Findings

- ✅ Inbox query is indexed and N+1-free (eager-loads `participants` +
  `lastMessage`; relies on `(participant_type, participant_id)` and
  `last_message_at` indexes).
- ✅ Unread totals use a `SUM`/`COUNT` over the denormalized `unread_count`
  column — no message scanning.
- ⚠️ **PF3:** `messages()` is unbounded by default (no LIMIT, no pagination) and
  F3 makes a bad/negative limit silently unbounded too.
- ⚠️ `guardNotBlocked` issues a `lockForUpdate` over all participant rows on every
  send — fine for 2-party threads, but a serialization point under heavy
  same-conversation concurrency.

---

## Documentation Findings

- ✅ Installation, setup, usage, events, broadcasting, authorization and security
  notes are all present and unusually candid about trade-offs. Publish tags
  (`messenger-migrations`, `messenger-config`) are regression-tested.
- ⚠️ **DX2:** The README usage block shows `'reply_to' => $previousMessage`
  without noting that a reply on a brand-new conversation is always rejected
  (only mentioned in a pipe docblock).
- ⚠️ The MySQL body-length caveat (P1) is undocumented; the `text` column and a
  20k-character limit are presented as compatible.
- ⚠️ `markAsUnread`'s "sets count to 1 / no received-message check" behavior is
  undocumented.

---

## Developer Experience Findings

- ✅ `composer install` clean; suite runs first try (179 passed).
- ✅ Swappable models, configurable tables/pipeline, clear facade API.
- ⚠️ Migrations ship as `.php.stub` and must be published before `migrate`
  (documented, recently hardened). Slightly unusual but works.
- ⚠️ The `$guarded = []` + "only write through actions" contract relies entirely
  on the developer reading the security note.

---

## Unexpected Behaviors (discovered)

1. A negative read `limit` returns the **entire** table; `limit: 0` returns
   nothing (F3).
2. A user can hold an **unclearable unread badge** with zero readable messages
   (F1/probe 3).
3. You can message a user **who does not exist in the database** (S5/probe 12).
4. A 20,000-character message that passes validation can be **silently truncated
   by MySQL** (P1).

---

## Recommended Fixes (prioritized)

1. **P1:** Change `body` to `mediumText`/`longText` *or* enforce the limit in
   bytes; align default with the column. *(High)*
2. **D1:** Document + provide tooling for participant deletion (a prune
   command and/or an `onParticipantDeleted` hook); make `sender`/`participant`
   accessors null-safe in examples. *(High)*
3. **D2:** Ship an attachment GC / orphan-prune artisan command. *(Medium)*
4. **F1:** In `markAsUnread`, require an existing received message (and/or refuse
   when `cleared_at` hides everything); otherwise keep state coherent. *(Medium)*
5. **F3 / PF3:** Validate `limit` (reject ≤ 0 or clamp), add a sane default cap
   and a pagination/keyset helper to `messages()`. *(Medium)*
6. **P2 / P3:** Validate/limit attachment filename length; reject zero-byte
   uploads. *(Low–Medium)*
7. **S4:** Harden models (explicit `$fillable`, or keep `$guarded` but add a
   bold "never `::create($request->all())`" callout + example). *(Medium)*
8. **S1/S2:** Ship a sample authorized-download controller and make the
   public-disk warning louder; consider `temporaryUrl()` in examples. *(Medium)*
9. **S5:** Optionally validate that participants exist (or document the ghost
   behavior explicitly). *(Low)*
10. **C2:** Make `markAsRead`/`clear` resets concurrency-safe relative to inbound
    increments (e.g. conditional update / row lock). *(Low)*

---

## Release Decision

### RELEASE WITH CAUTION

**Justification.** The domain model, send-path concurrency hardening, event
design and test coverage are above-average and the package does what it claims.
But for a commercial release to thousands of production apps it ships with: a
**MySQL data-loss/length mismatch** on the single most-used field (P1), **no
referential integrity or orphan/attachment GC** (D1/D2), **unvalidated unbounded
read limits** (F3/PF3), and **incoherent `markAsUnread` state** (F1). None of
these corrupt the happy path on SQLite — which is exactly why the green suite is
misleading. Fix P1 + D1/D2 + F3 before tagging a 1.0; the remainder are fast
follow-ups. With P1 and the integrity items addressed, this is comfortably an
8/10 package.
