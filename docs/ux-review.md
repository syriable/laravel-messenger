# Team F — UX Review

> Critique of the reference experience (Fiverr-style inbox) and a prioritised set
> of improvements benchmarked against WhatsApp, Telegram, Slack, Facebook
> Messenger and Discord. Goal: a **sellable, modern, performant** messenger — not
> a 1:1 Fiverr clone.

## 1. Strengths of the reference

- **Clear 3-pane mental model** (list · thread · context) that scales from
  consumer to operator use.
- **Flat, readable message rows** (email-like) suit long, formal messages and a
  business context — less "toy chat", more "professional inbox".
- **Context card** ("This message relates to") grounds a conversation in a
  business object — a genuinely strong B2B pattern most chat apps lack.
- **Rich participant/About panel** gives instant trust signals (ratings, level,
  response time) — valuable for marketplaces/support.
- **Sensible per-message and per-conversation menus** cover the essential
  actions without clutter.
- **Considerate composer** — the explicit Enter-behaviour setting respects user
  preference (a detail Slack/WhatsApp also get right).

## 2. Weaknesses / friction

| # | Issue | Impact |
|---|-------|--------|
| 1 | **No visible delivery/read receipts** on messages | Users can't tell if a message landed/was seen — table-stakes vs. WhatsApp/Telegram. |
| 2 | **No typing indicator** | Conversation feels static; no presence-of-attention. |
| 3 | **Flat rows lack speaker asymmetry** | Self vs. other is only avatar/name — slower to scan than aligned bubbles; cognitively heavier on mobile. |
| 4 | **No message grouping / date separators** visible | Long threads become a wall; harder to locate "yesterday". |
| 5 | **Hover-only message actions** | Invisible on touch; discoverability poor. |
| 6 | **"Delete" is ambiguous** | Users expect destructive delete; product does a visibility clear — mismatch of expectation. |
| 7 | **No reactions / quick replies** | Modern baseline (Messenger/Slack/Discord) absent; every ack costs a full message. |
| 8 | **Search is shallow** (list only) | No in-thread search/jump-to-message. |
| 9 | **No clear offline/failed-send affordance** in the static view | Reliability anxiety on flaky networks. |
| 10 | **Ads/upsell interleaved in the list** | Acceptable for Fiverr's model; for a sellable package it must be an optional slot, off by default. |
| 11 | **No unread divider** ("new messages" line) in-thread | Users lose their place after returning. |
| 12 | **Limited accessibility cues** (color-only states, hover affordances) | Keyboard/screen-reader/touch users underserved. |

## 3. Benchmark — patterns worth adopting

| Pattern | Source | Adopt? | Notes |
|---------|--------|:---:|-------|
| Read receipts (✓ / ✓✓ / "Seen 22:55") | WhatsApp, Messenger | **Yes (P1)** | Derive from `last_read_at`; live via broadcast. |
| Typing indicator | All | **Yes (P1)** | Ephemeral client event. |
| Presence + "last seen" | WhatsApp, Telegram | **Yes (P1)** | Via `PresenceResolver`; privacy-respecting. |
| Message reactions (emoji) | Messenger, Slack, Discord | **Yes (P2)** | Lightweight, high-value; new additive table. |
| Reply with quoted preview | All | **Yes (P1)** | Map to existing `reply_to_id`; richer preview. |
| Unread divider + jump-to-bottom w/ count | Slack, Discord | **Yes (P1)** | Restores place; pairs with the scroll FAB already in the UI. |
| Date separators + smart grouping | All | **Yes (P1)** | Reduce wall-of-text. |
| Edit / delete / unsend | Telegram, WhatsApp, Slack | **Consider (P3)** | Conflicts with v1 immutability; needs domain decision + tombstones. Out of scope for v1, design-ahead. |
| Pinned messages | Telegram, Slack, Discord | **Consider (P3)** | Could reuse the Saved/bookmark mechanic at conversation scope. |
| Threaded replies | Slack, Discord | **No** | Package is deliberately non-threaded; keep flat replies. |
| Voice messages | WhatsApp, Messenger | **Consider (P3)** | Just another attachment type; needs recorder UI. |
| Link previews / unfurls | Slack, Telegram, Discord | **Consider (P2)** | Server-side unfurl + render; privacy/caching considerations. |
| Drafts persisted per conversation | Slack, WhatsApp | **Yes (P2)** | Local-first; optional server sync. |
| Slash/quick commands, canned replies | Slack | **Consider (P3)** | Strong for support/operator use (Filament). |
| Message search w/ jump | All | **Yes (P2)** | In-thread search + scroll-to. |
| Forward / share message | All | **Consider (P3)** | Cross-conversation send. |

## 4. Recommended UX direction for the package

1. **Two message-display modes, themeable:** keep the reference **"flat/email"**
   mode (great for B2B/marketplace/support) **and** ship a **"bubble"** mode
   (aligned, colored, grouped — familiar consumer chat). One token switch. This
   is a major selling point.
2. **Receipts + typing + presence as a first-class trio (P1).** This closes the
   biggest perceived-quality gap versus the named apps and is cheap given the
   existing `last_read_at`/broadcast foundation.
3. **In-thread orientation aids (P1):** date separators, an unread divider, and a
   jump-to-bottom-with-count FAB.
4. **Touch-first action model:** replace hover-only menus with an always-present
   affordance + long-press; menus become bottom sheets on mobile.
5. **Honest destructive language:** rename/clarify "Delete" → "Clear chat"
   (matches the `clear` semantics) and reserve "Delete" for a genuinely
   destructive host-level action if ever added.
6. **Reactions (P2)** as the next high-ROI feature once the trio lands.
7. **Accessibility & RTL as acceptance gates, not afterthoughts** — keyboard
   paths, ARIA `feed`/`log`, non-color state encoding, full RTL mirroring (the
   maintainer's primary locale is Arabic).
8. **Everything host-skinnable** — tokens, slots (safety banner, profile panel,
   list ad slot, context card), and optional features off by default, so the
   package is commercially reusable without looking like Fiverr.

## 5. UX acceptance themes (feed into stories)

- A user can always tell a message's **state** (sending/sent/read/failed)
  without relying on color alone.
- A returning user lands on their **unread divider** and can **jump to bottom**
  with a count.
- Every hover action is reachable by **keyboard and touch**.
- Destructive-sounding actions **match their real effect**.
- The thread is **scannable** (grouping, separators, readable measure).
- The whole UI works **without realtime** (polling fallback) and **in RTL**.
</content>
