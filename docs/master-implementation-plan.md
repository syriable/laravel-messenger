# Master Implementation Plan (Fan-In)

> Consolidated output of Teams A–F. This is the single source of truth for
> building **Laravel Messenger** into a full-stack, sellable solution:
> headless domain (existing) + Livewire 4 UI + Filament integration, on
> Laravel 12+.

## 0. Executive summary

The existing `syriable/laravel-messenger` is a mature, well-architected,
**headless one-to-one messaging domain** that already covers ~90% of the
backend a Fiverr-style inbox needs (inbox, messages with keyset pagination,
send pipeline, attachments, unread counters, star/archive/block/spam/clear,
reporting, optional broadcasting). The work is therefore **mostly additive and
front-of-house**:

1. A new **`syriable/laravel-messenger-ui`** package — Livewire 4 components,
   theme tokens, Echo/realtime, polling fallback.
2. A new **`syriable/laravel-messenger-filament`** plugin — thin glue mounting
   the UI as a Page + admin/moderation Resources/Widgets.
3. A small set of **additive domain features** — saved messages, search,
   `unread`/`spam` inbox scopes, presence/typing contracts, read-receipt &
   inbox broadcasts, optional notifications — none of which break BC.

**Recommendation:** three-package architecture, domain stays headless,
extend-by-composition, ship in priority waves (P0→P3). Estimated **~10–14
engineer-weeks** to a sellable v1 (P0+P1), detail in §10 and the backlog.

## 1. Final architecture

```
┌──────────────────────── host Laravel 12+ app ────────────────────────┐
│                                                                       │
│   syriable/laravel-messenger-filament  (optional)                     │
│     • MessengerPlugin (chat/moderation/widgets)                       │
│     • ChatPage (mounts <livewire:messenger/>)                         │
│     • MessageReport / Conversation / Message resources + widgets      │
│                  │ mounts / calls                                     │
│                  ▼                                                     │
│   syriable/laravel-messenger-ui  (Livewire 4 + theme + Echo)         │
│     • Messenger root, Sidebar, Thread, Composer, ProfilePanel …       │
│     • Presenter/Presence/Search resolvers (host-bound)                │
│     • channels.php, Echo wiring, polling fallback, tokens/preset      │
│                  │ public API only                                    │
│                  ▼                                                     │
│   syriable/laravel-messenger  (existing headless domain — additive)   │
│     • Messenger facade · Actions · Queries · Pipeline · Events        │
│     • Models (config-swappable) · Broadcasts · migrations             │
│     + NEW: SavedMessage, Search queries, inbox scopes, presence/typing │
│       contracts, ConversationReadBroadcast, inbox broadcast, notifs   │
└───────────────────────────────────────────────────────────────────────┘
        Reverb/Pusher (optional)        Storage disk (attachments)
```

**Principles:** domain stays headless & dependency-free; UI consumes only the
public API; Filament is thin glue; everything additive & BC-safe; optional
features off by default; themeable & RTL-first.

## 2. UI architecture (from Team A/F)

- Persistent **3-column shell**: list rail · thread · profile/About (slot-driven).
- **Two message styles** (themeable): flat/email (reference) **and** bubble.
- Token layer (`--msgr-*`) + Tailwind preset; neutral default theme (not Fiverr
  green); slots for safety banner, profile, list-ad, context card.
- Responsive: 3-col (xl) → 2-col+toggle (lg) → master-detail (md) → single
  column/bottom-sheets (sm). Touch-first action affordances. Full RTL + a11y.
- States enumerated per surface in `ui-analysis.md` §9.

## 3. Livewire architecture (from Team C)

- Component tree rooted at a full-page `Messenger` component (also a Filament
  Page), with **islands** for Sidebar, Thread, Composer, ProfilePanel, SavedPanel
  so composer keystrokes never re-render the message list.
- **URL-bound state** (`#[Url] conversationId/scope/q`), `#[Computed]` queries,
  Livewire events between islands, optimistic send.
- **Realtime:** Echo + Reverb on the package's `message.sent` broadcast, plus new
  read/typing/inbox signals; **polling fallback** (`wire:poll.visible`, adaptive)
  when broadcasting is off.
- **Lazy** components + `wire:navigate` shell reuse; **infinite scroll** via the
  existing keyset pagination (`before_id`/`after_id`) with scroll-offset
  preservation and a bounded DOM window.

## 4. Filament architecture (from Team D)

- `MessengerPlugin` (booleans: `chat`, `moderation`, `widgets`), one `Messaging`
  cluster.
- `ChatPage` mounts the UI full-width (no logic duplication).
- Resources: `MessageReport` (rich moderation queue), `Conversation` &
  `Message` (read-only, audit). Widgets: open reports, messages/day, active
  conversations, blocked/spam, latest reports.
- Filament Actions are thin wrappers over domain Actions/Queries; policies live
  in Filament/host; **no domain logic in Filament**.

## 5. Package integration strategy (from Team E)

- Three packages; domain unchanged in public surface.
- New features additive: `SavedMessage`, search queries + `ParticipantSearch
  Resolver`, inbox `unread`/`only_spam` options, `PresenceResolver`, typing
  convention, `ConversationReadBroadcast`, inbox broadcast, opt-in notifications,
  `ParticipantPresenter` (UI-side).
- BC contract enforced by a snapshot/architecture test; no edits to existing
  migrations/events/signatures.

## 6. Database changes

| Change | Package | Type | Priority |
|--------|---------|------|----------|
| `messenger_saved_messages` table | domain | new migration stub | P1 |
| `messenger_message_reactions` table (reactions) | domain | new migration stub | P2 |
| `messenger_messages` fulltext index (optional) | domain | new optional stub | P2 |
| `message_reports.resolved_at/resolved_by` (triage) | filament | new migration | P2 |
| (presence/typing/read receipts) | — | **no schema** (transport) | P1/P2 |

All additive; existing five migrations untouched.

## 7. Realtime strategy

- **Default transport:** Laravel Reverb (Pusher-compatible), via Laravel Echo.
- **Channels:** `messenger.conversation.{id}` (private — messages, read, typing),
  `messenger.user.{id}` (private — inbox signals), `messenger.presence.{scope}`
  (presence). Auth via shipped `channels.php`; default participant = auth user.
- **Signals:** `message.sent` (existing), `conversation.read` (new),
  `typing` (client event), inbox-activity (new). Payloads scalar; DB
  authoritative; attachments re-loaded on demand.
- **Degradation:** everything works with broadcasting off via adaptive
  `.visible` polling. A single capability flag prevents poll+subscribe overlap.

## 8. Event flow diagrams

### 8.1 Send message (happy path, realtime on)

```
User (Composer island)
  │ submit (optimistic row appended w/ nonce)
  ▼
Messenger::send(from,to,NewMessage)
  ▼ SendMessageAction
    ├─ run send pipeline (validity, block/spam, content, attachments, reply)
    ├─ store attachments (disk)  ──(rollback⇒delete files)
    ├─ DB tx: create conv if first msg (race-recover) · insert message
    │         · update conv.last_message_* · atomic recipient unread++
    └─ DB::afterCommit ⇒ dispatch MessageSent (+ ConversationCreated if new)
  ▼
BroadcastMessageSent listener (if broadcasting.enabled)
  ▼ MessageSentBroadcast on messenger.conversation.{id} (.message.sent)
  ├─► Recipient Thread island: patch MessageList, mark read (if open)
  ├─► Both Sidebars: bump conversation, update snippet/time/unread
  └─► Sender Composer: reconcile optimistic row by nonce → "Sent"
  ▼ inbox-activity broadcast on messenger.user.{recipient}
  └─► Recipient Sidebar updates even with no conversation open
```

### 8.2 Read receipt

```
Recipient opens thread / scrolls to bottom
  ▼ Messenger::markAsRead(conv,participant)  (row-locked unread reset → 0)
  ▼ DB::afterCommit ⇒ ConversationRead event
  ▼ BroadcastConversationRead listener (new, if enabled)
  ▼ ConversationReadBroadcast on messenger.conversation.{id} (.conversation.read)
  └─► Sender Thread island: flip last message(s) ≤ read_at to "Read"
```

### 8.3 Typing

```
User types (debounced) ─► Echo client/whisper "typing" on conversation channel
  └─► Other participant Thread island: show TypingIndicator (auto-expire ~4s)
  (no server, no DB)
```

### 8.4 Moderation (Filament)

```
Operator (MessageReportResource) ─► Action "Move to spam"
  ▼ Messenger::spam(conversation, …)  → SpamConversationAction
  ▼ ConversationMarkedAsSpam event → (optional notify) → table refresh
```

## 9. Component tree (canonical)

See `livewire-architecture.md` §2. Summary:
`Messenger › { Sidebar › [ListHeader, ConversationList › ConversationListItem,
InboxEmptyState], Thread › [ThreadHeader, SafetyBanner, MessageList › (Date
Separator, MessageRow › MessageActionsMenu), TypingIndicator, ScrollToBottomFab,
Composer › ComposerToolbar/EnterBehaviourPopover], SavedPanel, ProfilePanel }`.

## 10. Development roadmap (waves)

| Wave | Scope | Outcome | Est. |
|------|-------|---------|------|
| **P0 — Foundations** | UI package skeleton, theme tokens/preset, presenter contract, shell layout, Sidebar+inbox (read), Thread+messages (read, keyset, lazy), Composer send (text+attachments+reply), empty states, polling baseline. Domain: inbox `unread`/`only_spam` scopes, `ParticipantPresenter`. | A working, sellable **read+send** messenger over the existing domain, no realtime required. | ~4–5 wks |
| **P1 — Parity & realtime** | Echo/Reverb wiring + `channels.php`; live new-message, presence + last-seen (`PresenceResolver`), typing, read receipts (`ConversationReadBroadcast`), unread divider + jump-to-bottom, date separators/grouping, filters dropdown, inbox search (+`ParticipantSearchResolver`), saved messages + Saved tab, conversation/message menus wired to actions, responsive + touch + RTL + a11y pass. Filament: plugin + ChatPage + MessageReport resource + core widgets. | **Feature-parity v1** with the reference + modern trio; Filament moderation. | ~5–7 wks |
| **P2 — Modern delight** | Reactions, link unfurls, in-thread search + jump, drafts, notifications (opt-in), bubble theme mode, Conversation/Message read-only resources, more widgets, message-body fulltext search. | Differentiated, premium feel. | ~3–4 wks |
| **P3 — Advanced/optional** | Edit/delete/unsend (needs domain immutability decision + tombstones), pinned messages, voice messages, forward/share, slash/canned replies (operator), multi-device sync polish. | Power features; some require domain RFCs. | scoped later |

(Waves can overlap; P0 and the domain-additive bits parallelise across the two
new packages.)

## 11. Risks & mitigations (summary; full register in `backlog.md` §6)

- **Realtime ops complexity (Reverb infra)** → ship polling-first; realtime is
  additive and capability-gated.
- **Scroll/pagination jank on long threads** → keyset + offset preservation +
  bounded DOM + stable `wire:key`; perf budget tests.
- **Host identity/presence assumptions** → solved via contracts with safe
  defaults; never read host columns directly.
- **BC drift in the domain** → contract snapshot test; additive-only rule.
- **"Delete" semantics mismatch** → rename to "Clear chat"; product sign-off.
- **Immutability vs. edit/delete demand** → defer to P3 behind a domain RFC.
- **Filament version coupling** → isolate in the Filament package; matrix test.
- **RTL/a11y debt** → acceptance gates, not afterthoughts.

## 12. Open product decisions (need sign-off before P1)

1. "Delete" → confirm **Clear chat** (per-participant visibility reset).
2. Per-message "Move to spambox" → confirm **escalates the conversation** to
   spam (no per-message spam in v1).
3. Default theme = neutral (not Fiverr green) + ship both flat & bubble modes —
   confirm.
4. Realtime default transport = **Reverb** — confirm (vs. Pusher/Ably).
5. Saved messages as a domain feature (vs. host-only) — confirm domain
   placement.
6. Notifications default **off**, opt-in — confirm.
</content>
