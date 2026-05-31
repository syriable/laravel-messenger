# Team A — UI Reverse Engineering

> Reverse-engineered from the 5 reference screenshots (Fiverr-style one-to-one
> inbox). This document is the **visual + interaction source of truth**; the
> product spec (Team B) turns it into behaviour, and the Livewire spec (Team C)
> turns it into components.

## 0. Source material (what each screenshot shows)

| # | Screen | Key signals |
|---|--------|-------------|
| 1 | **Inbox + empty state** | Left rail conversation list; right pane "Pick up where you left off / Select a conversation and chat away" with illustration. |
| 2 | **Conversation open** | 3-pane layout: list \| thread \| profile. Header with presence + verified badges, `Messages / Saved` tabs, safety banner, flat message rows, a "This message relates to" context card, composer. |
| 3 | **Message hover menu** | Per-message `...` → Reply · Save · Move to spambox · Report. |
| 4 | **Composer Enter-behaviour popover** | "Pressing Enter will: Start a new line (⌘+Enter to send) / Send message (Shift+Enter for newline)". |
| 5 | **Conversation `...` menu** | Header `...` → Mark as unread · Move to archive · Delete. |

## 1. Layout structure

The app is a **persistent 3-column shell** inside the host application chrome
(top global nav is out of scope — it belongs to the host).

```
┌───────────────────────────────────────────────────────────────────────────┐
│ (host global nav — out of scope)                                            │
├───────────────┬─────────────────────────────────────┬───────────────────────┤
│  COLUMN 1      │  COLUMN 2                            │  COLUMN 3             │
│  Conversation  │  Conversation thread                │  Participant profile  │
│  list rail     │                                     │  ("About") panel      │
│  ~320–360px    │  fluid (flex-1)                     │  ~300–340px           │
│                │                                     │                       │
│  • List header │  • Thread header (presence, actions)│  • Identity block     │
│    (filter +   │  • Tabs: Messages / Saved           │  • Attributes list    │
│     search)    │  • Safety banner                    │  • Activity / stats   │
│  • Conversation│  • Message scroll region            │  • Upsell card        │
│    items       │  • Composer (sticky bottom)         │                       │
└───────────────┴─────────────────────────────────────┴───────────────────────┘
```

- **Column 1 (list rail)** — fixed width, full height, independently scrollable.
- **Column 2 (thread)** — fluid; header pinned top, composer pinned bottom,
  message region scrolls between them. **Newest message at the bottom**; the
  region is bottom-anchored on open.
- **Column 3 (profile/About)** — fixed width, full height. **Host-owned
  content** (Fiverr seller stats). For our package this is an *optional,
  slot-driven* panel — we render an identity header and expose a slot; the host
  fills the rest.

When no conversation is selected, **Column 2 = empty state** and **Column 3 is
hidden** (screenshot 1).

## 2. Navigation patterns

| Pattern | Behaviour |
|---------|-----------|
| **Master–detail** | Selecting an item in Column 1 loads it into Column 2; the rail stays put (no full page nav). Selection should be URL-addressable (`/messages/{conversation}`). |
| **Filter dropdown** | "All messages ▾" in the list header — switches the list scope (All / Unread / Starred / Archived / Spam — exact set inferred, see §9 ambiguities). |
| **Search** | Magnifier icon in the list header toggles an inline search field that filters the list. |
| **Tabs (thread)** | `Messages` / `Saved` segmented control scoped to the open conversation. `Saved` = messages the user bookmarked via the per-message *Save* action. |
| **Header `...` menu** | Conversation-level actions: Mark as unread, Move to archive, Delete. |
| **Per-message `...` menu** | Message-level actions: Reply, Save, Move to spambox, Report (appears on row hover, top-right of the row). |
| **Profile deep-link** | Name/`@handle` in the thread header links to the participant's host profile (out of scope). |

## 3. Chat patterns

- **Flat rows, not chat bubbles.** Each message is a full-width row:
  `avatar · sender name · body (multi-paragraph) · timestamp (top-right)`.
  There is **no left/right alignment** and no colored bubble — sender vs. self
  is distinguished only by avatar/name, not by side or color. (This is a
  deliberate "email-like" style; our component must support **both** this flat
  style and a classic bubble style via a theme token — see Design System.)
- **"Me" rows** use a generic monogram avatar and the literal label `Me`.
- **Reply / quote card** — "This message relates to:" renders a referenced
  object (here a Fiverr gig) as a bordered card above the message. In our domain
  the equivalent is a **reply-to preview** (the `reply_to_id` message) and/or a
  host-supplied **context card**.
- **Day/▪ grouping** — messages carry absolute timestamps (`09 May, 23:07`).
  Consecutive messages keep individual timestamps; no compact grouping is shown,
  but we should support date separators.
- **Safety banner** — a pinned, dismissible informational strip at the top of
  the thread ("WE HAVE YOUR BACK … keep payments within Fiverr"). For us this is
  an optional **slot** at the top of the message region.
- **Scroll behaviour** — opens pinned to bottom; older messages load on
  scroll-up (infinite scroll, keyset). A floating "scroll to bottom" chevron
  appears when scrolled up (visible bottom-right in screenshots 3 & 5).

## 4. Component inventory

Atomic → composite, with the props each needs.

| Component | Role | Key props / state |
|-----------|------|-------------------|
| `Avatar` | Circular image w/ presence dot + verified tick | `src`, `presence(online\|away\|offline)`, `verified`, `size` |
| `PresenceDot` | Online/away indicator overlay | `status` |
| `Badge` | "Vetted Pro", "Top Rated ◆◆◆", "AD", "PLUS" | `label`, `variant` |
| `UnreadCounter` | Pill with count (Edward K = 2, Tareq = 1) | `count` |
| `StarToggle` | Outline/filled star on list item & header | `starred`, `onToggle` |
| `Timestamp` | Relative (3 weeks) / absolute (09 May, 23:07) | `value`, `format` |
| `ListHeader` | Filter dropdown + search toggle | `scope`, `searching` |
| `SearchField` | Inline list search | `query` |
| `FilterDropdown` | "All messages ▾" scope switcher | `options`, `selected` |
| `ConversationListItem` | One row in the rail | `avatar`, `name`, `verified`, `snippet`, `time`, `unread`, `starred`, `active`, `presence`, `isAd` |
| `AdSlot` | Sponsored row ("illustra Sol / More details") | host slot |
| `ConversationList` | Scrollable list of items | `items`, `activeId` |
| `ThreadHeader` | Identity + presence + actions | `participant`, `lastSeen`, actions |
| `ThreadTabs` | Messages / Saved | `active` |
| `SafetyBanner` | Dismissible info strip (slot) | `content` |
| `MessageRow` | One message (flat row) | `sender`, `body`, `time`, `isSelf`, `replyTo`, `attachments`, `contextCard` |
| `ReplyPreviewCard` | "This message relates to" / quoted message | `reference` |
| `AttachmentChip/Grid` | Rendered attachments | `attachments` |
| `MessageActionsMenu` | Reply · Save · Move to spambox · Report | per-message |
| `DateSeparator` | "Today" / date divider | `date` |
| `TypingIndicator` | "… is typing" (new — not in screenshots) | `who` |
| `ScrollToBottomFab` | Floating chevron | `visible`, `unreadBelow` |
| `Composer` | Input + toolbar + send | `value`, `attachments`, `enterToSend` |
| `ComposerToolbar` | `Aa` format · 📎 attach · 🙂 emoji | actions |
| `EnterBehaviourPopover` | Enter = newline vs. send | `setting` |
| `ConversationActionsMenu` | Mark unread · Archive · Delete | conversation-level |
| `ProfilePanel` | "About" identity + slot | `participant`, slot |
| `EmptyState` | "Pick up where you left off" | `illustration`, `title`, `subtitle` |

## 5. User flows

1. **Browse → open** — land on inbox (empty state) → click a conversation →
   thread loads, marked read, profile panel appears.
2. **Send** — type → Enter/Send (per Enter-behaviour setting) → optimistic row
   appended → server confirms → list re-sorts to top, snippet/time update.
3. **Reply to a message** — hover message → `...` → Reply → composer enters
   "replying to" mode with a quoted preview → send.
4. **Attach** — 📎 → pick files → chips appear in composer → send with body.
5. **Save / view saved** — hover → `...` → Save → message appears under `Saved`
   tab.
6. **Moderate a message** — hover → `...` → Report (reason/note) or Move to
   spambox.
7. **Manage conversation** — header `...` → Mark as unread / Move to archive /
   Delete; or star via header/list star toggle.
8. **Filter / search** — change scope dropdown or type in search to narrow the
   list.

## 6. Responsive behaviour

The screenshots are desktop (~2000px). Inferred breakpoints:

| Breakpoint | Behaviour |
|------------|-----------|
| **≥1280px (xl)** | Full 3-column shell as shown. |
| **1024–1279px (lg)** | Profile panel (Column 3) collapses to a toggle ("ⓘ"/avatar in header); 2 columns remain. |
| **768–1023px (md)** | Master–detail: list **or** thread. Selecting an item slides the thread over the list; a back arrow returns. |
| **<768px (sm)** | Single column, full-screen views. List = home; thread is a pushed route; composer docks to the bottom safe-area; menus become bottom sheets. |

Touch: hover-only affordances (per-message `...`, star) must also appear via
long-press / an always-visible affordance on touch.

## 7. Design system (extracted tokens)

The reference is a **clean, neutral, low-chrome** system (Fiverr's). Tokens we
will ship as CSS variables so hosts can re-skin:

- **Color**
  - Surface/background: white `#FFFFFF`; subtle row hover `~#F7F7F7`; active
    list item `~#EFEFF0` (light grey, no accent fill).
  - Text: primary near-black `~#222325`; secondary/muted grey `~#74767E`.
  - Accent/brand: green for links/CTAs ("More details →" `~#1DBF73`-family).
    **Parameterise** — this is Fiverr green; default ours to a neutral
    indigo/blue and let hosts override.
  - Badges: verified tick = soft indigo; "Top Rated" = warm gold/orange; unread
    counter = red/pink pill.
  - Borders/dividers: hairline `~#E4E5E7`.
- **Typography** — system/Inter-like sans. Sizes: name ~15px semibold, body
  ~14–15px regular, meta/timestamp ~12–13px muted. Generous line-height in body
  (~1.5).
- **Radius** — avatars fully round; cards/inputs ~8px; buttons ~6–8px.
- **Elevation** — flat overall; menus/popovers use a soft shadow + 1px border.
- **Iconography** — thin line icons (search, tag, star, paperclip, emoji, send,
  kebab, chevrons).
- **Density** — comfortable. List item ≈72px tall; message rows have ample
  vertical padding (~16–20px).

> **Decision:** ship a small token layer (`--msgr-*`) + a Tailwind preset.
> Default theme = neutral (not Fiverr green). Provide a "flat/email" message
> style and a "bubble" style, switchable by token. This keeps it **sellable**
> and re-skinnable per client.

## 8. Spacing & layout hierarchy

- **Rail item**: 12–16px horizontal padding; avatar 48px; 12px gap avatar→text;
  name row and snippet row stacked; time + star right-aligned.
- **Thread header**: ~16–20px padding; avatar 40px; actions right-aligned with
  ~16px gaps.
- **Message row**: ~12–16px vertical padding; avatar 32–40px; 12px gap; body
  max-width readable (~720px) though Fiverr lets it run wide.
- **Composer**: ~12–16px padding; toolbar row beneath input; send button
  bottom-right.
- **Profile panel**: ~20–24px padding; label/value rows with the value
  right-aligned (definition-list layout).

## 9. State variations

| Surface | States |
|---------|--------|
| **List item** | default · hover · active/selected · unread (bold name + counter) · starred · has-presence(online/away/offline) · verified · ad/sponsored · muted snippet "Me: …" prefix when last message is self. |
| **Inbox** | populated · empty ("Pick up where you left off") · loading (skeleton rows) · search-no-results · filtered-empty. |
| **Thread** | loading · loaded · empty (new conversation) · blocked/spam (composer disabled, banner) · cleared (history hidden until next message). |
| **Message row** | default · hover (reveals `...`) · self vs. other · with reply preview · with attachments · reported (subtle) · sending(optimistic)/failed(retry)/sent. |
| **Composer** | empty · typing · with attachments · disabled (blocked/spam) · sending · over-limit (length/attachment count) · enter-behaviour popover open. |
| **Presence** | online (green dot) · away/last-seen (text "Last seen 2 hours ago") · offline. |
| **Profile panel** | full (own/visible) · gated ("Join Seller Plus…" blurred stats) · hidden (no selection / small screens). |
| **Menus** | message menu, conversation menu, filter dropdown, enter-behaviour popover — each open/closed, keyboard-navigable. |

## 10. Ambiguities & resolutions

Where the screenshots are under-specified, we record the interpretations and the
chosen default (the package must stay one-to-one and headless-compatible).

1. **"Delete" (conversation menu)** — Fiverr soft-deletes per user.
   - (a) Hard delete · (b) Per-participant hide (like our `clear`) · (c) Archive
     variant.
   - **Chosen: (b)** map to a per-participant *visibility reset* using the
     existing `clear` semantics (no data loss, reappears on new message). Hard
     delete is out of scope (package is immutable). Surface copy stays "Delete"
     but documents the clear semantics. *Needs product confirmation.*
2. **"Move to spambox" (per-message)** — package spam is **conversation-level**.
   - (a) Move the whole conversation to spam · (b) introduce per-message spam.
   - **Chosen: (a)** the action escalates the conversation to spam (existing
     `spam()`), since v1 has no per-message spam and one-to-one makes
     per-message spam low-value. *Needs confirmation.*
3. **"Saved" tab / "Save" action** — no backend concept exists.
   - **Chosen:** new lightweight **SavedMessage** (bookmark) feature
     (participant ⨯ message), additive and per-participant. (See Team E/B.)
4. **Filter dropdown options** — only "All messages" is legible.
   - **Chosen:** All · Unread · Starred · Archived · Spam (the package already
     supports starred/archived/spam scoping in `inbox()` options).
5. **Presence & "Last seen"** — not modelled in the package.
   - **Chosen:** presence is **host/transport-derived** (presence channels +
     optional `last_seen_at` on the host user), exposed to the UI via a small
     resolver contract — *not* stored in the messaging tables. (See Team E.)
6. **Typing indicator** — not in screenshots but expected by parity targets.
   - **Chosen:** ephemeral client/whisper event over the conversation channel;
     never persisted.
7. **Rich text ("Aa")** — body is plain text in the package.
   - **Chosen:** ship plain-text + safe minimal markdown rendering; store raw
     text. Rich storage is a future option, not v1.
8. **Profile/About panel content** — entirely host domain (seller stats).
   - **Chosen:** render only identity (avatar/name/presence) + a host **slot**;
     do not invent profile fields in the package.
9. **Ad/sponsored row** — host concern.
   - **Chosen:** expose an injectable list slot; not a package feature.
</content>
