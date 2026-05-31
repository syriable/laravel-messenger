# Team B — Product Specification

> Turns the UI analysis (Team A) into testable product behaviour. Each feature
> lists: **What**, **Behaviour/Rules**, **States**, **Backend mapping** (what
> the current package already provides vs. **GAP** = new work), and
> **Acceptance criteria**.
>
> Legend — ✅ supported by current package · 🟡 partially supported · 🔴 GAP (new).

## Feature map at a glance

| Feature | Backend status |
|---------|----------------|
| Inbox (list, ordering, snippet, time) | ✅ `GetInboxConversationsQuery` |
| Conversation view (messages, clear boundary, pagination) | ✅ `GetConversationMessagesQuery` |
| Message composer (send, reply, attachments) | ✅ `SendMessageAction` / `NewMessage` |
| Attachments | ✅ `AttachmentService`, pipeline validation |
| Read status / unread counters | 🟡 `unread_count`, `last_read_at` (no per-message receipts) |
| Typing indicators | 🔴 GAP |
| User presence / last seen | 🔴 GAP (host/transport) |
| Search | 🔴 GAP (no search query) |
| Filters (All/Unread/Starred/Archived/Spam) | 🟡 inbox options exist; "Unread" filter is GAP |
| Saved messages tab | 🔴 GAP (new bookmark) |
| Empty states | UI only |
| Notifications | 🟡 events exist; notification layer is GAP |
| Star / Archive / Block / Spam / Clear / Report | ✅ Actions exist |
| "Delete" conversation | 🟡 maps to `clear` (see ambiguity #1) |

---

## 1. Inbox

**What** — The left-rail list of the participant's conversations, newest
activity first.

**Behaviour & rules**
- Ordered by `last_message_at` DESC (denormalized; unread never reorders).
- Each row: avatar + presence, name, verified/badge (host), last-message snippet
  (prefixed `Me:` when the last message is the viewer's), relative time, unread
  counter, star toggle.
- Archived conversations excluded from the default scope; available under the
  Archived filter.
- Blocked/spam conversations remain visible (only sending is blocked) per v1
  spec — unless the host opts to hide them.
- Cleared conversations are hidden until a newer message arrives.

**States** — populated · empty · loading(skeleton) · filtered-empty ·
search-no-results.

**Backend mapping** — ✅ `Messenger::inbox($p, $options)` with
`include_archived`, `starred`, `exclude_blocked`, `exclude_spam`, `limit`,
`with_participant_models`. 🔴 **GAP:** an `unread`-only scope and a search term
filter need to be added to the inbox query (additive options).

**Acceptance**
- Sending/receiving a message moves the conversation to the top.
- Unread count badge matches `participants.unread_count`.
- Opening a conversation clears its unread badge.
- Empty inbox shows the "Pick up where you left off" empty state.

## 2. Conversation view

**What** — The center thread: header, tabs, message stream, composer.

**Behaviour & rules**
- Messages chronological, newest at bottom; opens bottom-anchored.
- Respects the per-participant `cleared_at` boundary (history hidden after a
  clear until a new message).
- Infinite scroll upward via keyset pagination (`before_id`); newest via
  `after_id` for catch-up.
- Opening (or scrolling to bottom) marks the conversation read.
- Header shows identity, presence/last-seen, and conversation actions.

**States** — loading · loaded · brand-new(no messages yet) · blocked/spam
(composer disabled + banner) · cleared.

**Backend mapping** — ✅ `Messenger::messages($conversation, $participant,
['limit','before_id','after_id'])`; ✅ `markAsRead`. Per-message **read
receipts** are 🟡 derivable from the *other* participant's `last_read_at` (a
message is "Read" if `message.created_at <= otherParticipant.last_read_at`).

**Acceptance**
- Scroll-up loads older pages without losing scroll position.
- A blocked/spam conversation disables the composer and shows why.
- Re-opening a cleared conversation shows only post-clear messages.

## 3. Message composer

**What** — Bottom input: text, formatting (`Aa`), attach (📎), emoji (🙂), send,
and the Enter-behaviour setting.

**Behaviour & rules**
- Enter-behaviour is user-configurable: *Enter = newline (⌘/Ctrl+Enter sends)*
  **or** *Enter = send (Shift+Enter newline)* (screenshot 4). Persist per user
  (host-side preference; UI default = "Enter sends").
- Body max length enforced (`messenger.messages.max_body_length`, default
  20000); show counter near the limit.
- Optimistic append on send; reconcile on server confirm; show failed + retry.
- Reply mode: a quoted preview sits above the input with a dismiss (×).
- Attachment chips show before send; removable; respect count/size/type limits.
- Disabled when conversation is blocked/spam.

**States** — empty · typing · with-attachments · reply-mode · disabled ·
sending · over-limit · failed.

**Backend mapping** — ✅ `Messenger::send($from, $to, NewMessage|string|array)`
with `body`, `attachments` (UploadedFile[]), `reply_to`. Pipeline enforces all
rules. 🔴 **GAP (UI-only):** Enter-behaviour preference + rich-text/markdown
rendering (store raw text).

**Acceptance**
- Sending a valid message returns a persisted `Message` and updates the list.
- Empty body + no attachments is rejected (mirrors `EnsureMessageHasContent`).
- Over-limit / invalid attachment surfaces the pipeline exception as a field
  error, not a crash.

## 4. Attachments

**What** — Files attached to a message (images inline, others as chips).

**Behaviour & rules** — count ≤ `max_per_message` (10), size ≤ `max_size`
(10MB), extension/mime allow-listed; zero-byte rejected unless `allow_empty`.
Image attachments render as a thumbnail grid; documents as a download chip
(name, type, size). URL via `attachment.url` (disk-driven).

**States** — uploading · uploaded · failed · rejected(invalid).

**Backend mapping** — ✅ `AttachmentService` + `EnsureAttachmentsAreValid` +
`StoredAttachment`. Realtime broadcast does **not** carry attachment metadata;
the client re-loads the message to render attachments (documented behaviour).

**Acceptance** — invalid files are rejected pre-send with a clear message; valid
mixed (text+files) messages persist and render.

## 5. Typing indicators 🔴 GAP

**What** — "<name> is typing…" in the thread (and optionally the list row).

**Behaviour & rules** — ephemeral, never persisted. Emitted as the user types
(debounced ~1–2s, auto-expire ~3–4s of inactivity). Delivered over the
conversation's realtime channel via client/whisper events.

**Backend mapping** — 🔴 New: a `Typing` client event + a `messenger:typing`
broadcast convention. No DB. Requires realtime transport (Reverb/Pusher).

**Acceptance** — typing shows within ~1s and disappears within ~4s of stopping;
no DB writes; no-op when broadcasting disabled (graceful degradation).

## 6. Read status

**What** — Sender sees whether their message was read (Sent / Read).

**Behaviour & rules** — Derive from the recipient's `last_read_at`: a message is
**Read** when `message.created_at <= recipient.last_read_at`, else **Sent**.
Optional realtime "read" signal to update live. Group avatar/"Read 22:55" on the
last read message.

**Backend mapping** — 🟡 Derivable from existing `last_read_at`. 🔴 **GAP
(optional):** a `ConversationRead` **broadcast** projection so the sender updates
live (today `ConversationRead` is a server-side event only).

**Acceptance** — after the recipient opens the thread, the sender's last message
flips to "Read" (live if broadcasting on, on next load otherwise).

## 7. Unread counters

**What** — Per-conversation badge + global unread total.

**Behaviour & rules** — `participants.unread_count` per conversation; global via
`Messenger::unreadCount()` (excludes archived by default) and
`unreadConversations()`. Atomic increment on inbound send; reset to 0 on read
under a row lock.

**Backend mapping** — ✅ fully supported.

**Acceptance** — counts are race-safe under concurrent send/read; opening resets
to 0; manual "mark unread" sets the badge to 1.

## 8. Search 🔴 GAP

**What** — Search the inbox (and optionally within a conversation).

**Behaviour & rules**
- **Inbox search:** match on the other participant's display name/handle and/or
  last-message snippet. Name resolution is host-owned, so search needs a
  host-provided strategy (resolve participant → searchable name) **or** a
  message-body LIKE/full-text search.
- **In-conversation search (P2):** match message bodies within the thread.

**Backend mapping** — 🔴 New `SearchInboxQuery` / `SearchMessagesQuery`. For
name search, define a `ParticipantSearchResolver` contract the host implements
(the package can't know how names are stored). Body search uses indexed
LIKE/MySQL FULLTEXT (add a fulltext index migration — optional).

**Acceptance** — typing in search filters the list to matches; empty result
shows the no-results state; clears on reset.

## 9. Filters

**What** — "All messages ▾" scope switcher.

**Behaviour & rules** — scopes: **All · Unread · Starred · Archived · Spam**.
Persist last-used scope per user (UI/host preference).

**Backend mapping** — 🟡 `inbox()` supports starred/archived/exclude_*; 🔴
**GAP:** an `unread`-only scope (filter to `unread_count > 0`) and a dedicated
Spam scope (today spam is included, not isolatable) — add additive inbox
options.

**Acceptance** — each scope returns the correct subset; counts shown where
relevant (e.g., Unread badge on the filter).

## 10. User presence 🔴 GAP

**What** — online dot / "Last seen 2 hours ago — 22:54 local time".

**Behaviour & rules** — presence is **transport-derived** (presence channels)
and/or a host `last_seen_at`. The package must not store presence in messaging
tables; it exposes a `PresenceResolver` contract (`isOnline($participant)`,
`lastSeenAt($participant)`) the host can implement, plus a default presence-
channel-backed resolver for the Livewire UI. Local-time display uses the
viewer's / participant's timezone (host-provided).

**Acceptance** — presence reflects real connection state when channels are
configured; degrades to "last seen"/hidden otherwise; never written to messaging
tables.

## 11. Empty states

**What** — Inbox empty ("Pick up where you left off / Select a conversation and
chat away" + illustration); thread empty (new conversation); search-no-results;
filtered-empty.

**Backend mapping** — UI only. Copy/illustration configurable via slots.

**Acceptance** — each empty surface renders its dedicated state, not a blank
pane.

## 12. Notifications 🟡

**What** — Notify a participant of a new message (in-app toast, badge, optional
push/email/database notification).

**Behaviour & rules** — driven by the existing `MessageSent` domain event.
Provide an optional listener that dispatches a Laravel Notification
(database/broadcast/mail channels) to the recipient, and an in-app toast via the
realtime channel. Respect per-user mute (host preference). Must be opt-in.

**Backend mapping** — 🟡 `MessageSent` exists; 🔴 **GAP:** an opt-in
`NotifyRecipient` listener + a shipped `NewMessageNotification` + mute hooks.

**Acceptance** — with notifications enabled, the recipient gets exactly one
notification per delivered message; muted conversations produce none.

---

## Cross-cutting: per-participant actions (✅ all supported)

| Action | API | UI surface |
|--------|-----|------------|
| Star / Unstar | `star` / `unstar` | list + header star |
| Archive / Unarchive | `archive` / `unarchive` | header `...` "Move to archive" |
| Block / Unblock | `block` / `unblock` | (profile/host menu) |
| Spam / Unspam | `spam` / `unspam` | message "Move to spambox" → conversation spam |
| Clear (= "Delete") | `clear` | header `...` "Delete" (see ambiguity #1) |
| Mark read / unread | `markAsRead` / `markAsUnread` | header `...` "Mark as unread" |
| Report message | `report` | message `...` "Report" |
| Save message (bookmark) | 🔴 GAP new `save`/`unsave` | message `...` "Save" → Saved tab |

## New backend features required (summary for Team E)

1. **SavedMessage** (bookmark) — participant ⨯ message, additive table + actions
   + `Saved` query. (P1)
2. **Search** — `SearchInboxQuery` + optional body fulltext; `ParticipantSearch
   Resolver` contract. (P1/P2)
3. **Inbox `unread` scope** + isolatable **Spam** scope (additive options). (P1)
4. **Presence** — `PresenceResolver` contract + presence-channel default. (P1)
5. **Typing** — client-event convention over the conversation channel. (P1)
6. **Read-receipt broadcast** — `ConversationReadBroadcast` projection. (P2)
7. **Notifications** — opt-in `NotifyRecipient` listener + notification class +
   mute. (P2)
8. **Composer Enter-behaviour preference** — UI/host preference (no package DB).
   (P2)
</content>
