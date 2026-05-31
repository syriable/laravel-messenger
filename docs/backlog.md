# Backlog, Proposed GitHub Issues, Estimates & Risks

> Implementation backlog for the full-stack Laravel Messenger. Hierarchy:
> **Epic → Feature → Story → Task**. Priorities **P0–P3**. Estimates in
> story-points (SP, ~½ day each) and rolled up per epic. The "Proposed GitHub
> Issues" in §5 are ready to be created (one per Feature/Story) — they are
> **proposed**, not yet created (see the note at the end).

## 1. Priority legend

- **P0** — foundational; nothing ships without it.
- **P1** — required for a sellable, parity v1.
- **P2** — modern delight / differentiation.
- **P3** — advanced/optional; may need a domain RFC.

## 2. Epic overview

| Epic | Theme | Priority | Pkg | SP |
|------|-------|:---:|-----|---:|
| **E1** | Domain additive features | P0–P2 | domain | 34 |
| **E2** | UI package foundations (theme, shell, presenter) | P0 | ui | 26 |
| **E3** | Inbox & conversation list | P0–P1 | ui | 21 |
| **E4** | Conversation thread & messages | P0–P1 | ui | 29 |
| **E5** | Composer & sending | P0–P1 | ui | 21 |
| **E6** | Realtime, presence & typing | P1 | ui+domain | 26 |
| **E7** | Read receipts & unread UX | P1 | ui+domain | 16 |
| **E8** | Search, filters & saved messages | P1 | ui+domain | 21 |
| **E9** | Filament integration | P1–P2 | filament | 21 |
| **E10** | Modern delight (reactions, unfurls, drafts, notifications, bubble mode) | P2 | ui+domain | 29 |
| **E11** | Responsive, a11y, RTL, i18n | P1 | ui | 16 |
| **E12** | Testing, docs, packaging, CI | P0–P2 | all | 21 |
| **E13** | Advanced/optional (edit/delete, pins, voice, forward) | P3 | domain+ui | — |
| | | | **Total (P0–P2)** | **~281 SP** |

~281 SP ≈ ~140 engineer-days ≈ **~10–14 engineer-weeks** for P0–P2 (with the
usual overhead/parallelism caveats). P0+P1 alone ≈ **~9–11 weeks**.

---

## 3. Epics → Features → Stories

### E1 — Domain additive features (P0–P2, domain) — 34 SP
- **F1.1 Inbox scopes** (P0, 3) — add `unread` and `only_spam` options to
  `GetInboxConversationsQuery` + `inbox()` passthrough.
  - S: extend query options; S: tests; S: docs.
- **F1.2 ParticipantPresenter contract** (P0, 3) — `displayName/avatarUrl/handle/
  profileUrl/timezone`, default impl + binding.
- **F1.3 Saved messages** (P1, 8) — migration `messenger_saved_messages`, model,
  `Save/UnsaveMessageAction`, `GetSavedMessagesQuery`, `save/unsave/saved()`,
  `MessageSaved/Unsaved` events, tests.
- **F1.4 Search** (P1, 5) — `SearchInboxQuery`, `ParticipantSearchResolver`
  contract + default no-op; (P2) `SearchMessagesQuery` + optional fulltext stub.
- **F1.5 PresenceResolver contract** (P1, 3) — interface + presence-channel
  default + cache; no domain storage.
- **F1.6 ConversationReadBroadcast** (P1, 3) — listener on `ConversationRead`,
  new broadcast event, gated by config.
- **F1.7 Inbox-activity broadcast** (P1, 3) — `messenger.user.{id}` signal.
- **F1.8 Reactions domain** (P2, 5) — migration `messenger_message_reactions`,
  model, add/remove actions, query, events.
- **F1.9 Notifications** (P2, 3) — opt-in `NotifyRecipient` listener +
  `NewMessageNotification` + mute hook.
- **F1.10 BC contract test** (P0, 2) — snapshot public API/events/config.

### E2 — UI package foundations (P0, ui) — 26 SP
- **F2.1 Package scaffold** (P0, 5) — composer package, service provider, config,
  asset build (Vite), publish tags, depend on domain.
- **F2.2 Theme tokens + Tailwind preset** (P0, 5) — `--msgr-*` variables, neutral
  default, flat & bubble mode tokens, dark-mode-ready.
- **F2.3 3-column shell layout** (P0, 5) — responsive grid, slots (safety banner,
  profile, list-ad, context card).
- **F2.4 Presenter/Presence/Search resolver wiring** (P0, 3) — bind host impls;
  safe defaults.
- **F2.5 channels.php + auth defaults** (P0, 3) — participant=auth user;
  publishable.
- **F2.6 Base Blade components** (P0, 5) — Avatar/PresenceDot/Badge/UnreadCounter/
  StarToggle/Timestamp/EmptyState.

### E3 — Inbox & conversation list (P0–P1, ui) — 21 SP
- **F3.1 Sidebar + ConversationList** (P0, 5) — `#[Lazy]`, computed from
  `inbox()`, skeletons.
- **F3.2 ConversationListItem** (P0, 5) — avatar/presence/name/badge/snippet
  (`Me:` prefix)/time/unread/star, active state.
- **F3.3 InboxEmptyState** (P0, 2).
- **F3.4 Filter dropdown** (P1, 3) — All/Unread/Starred/Archived/Spam, URL-bound,
  persisted.
- **F3.5 Inbox realtime/poll updates** (P1, 3) — bump/sort/snippet/unread on
  events or `wire:poll.visible`.
- **F3.6 Star/Archive from list** (P1, 3) — actions + optimistic UI.

### E4 — Conversation thread & messages (P0–P1, ui) — 29 SP
- **F4.1 Thread island + ThreadHeader** (P0, 5) — identity, presence/last-seen,
  actions menu, Messages/Saved tabs.
- **F4.2 MessageList + MessageRow** (P0, 8) — `#[Lazy]`, flat style, stable keys,
  self/other, reply preview, attachments (image grid/doc chips), timestamps.
- **F4.3 Infinite scroll (older)** (P0, 5) — IntersectionObserver + `before_id` +
  offset preservation + bounded window.
- **F4.4 Catch-up (newer)** (P1, 3) — `after_id` on events/poll; "scroll to
  bottom" FAB with count.
- **F4.5 Date separators & grouping** (P1, 3).
- **F4.6 MessageActionsMenu** (P1, 3) — Reply/Save/Move-to-spambox/Report wired to
  domain (spambox → conversation spam; ambiguity #2); touch affordance.
- **F4.7 ConversationActionsMenu** (P1, 2) — Mark unread / Archive / "Clear chat"
  (ambiguity #1).

### E5 — Composer & sending (P0–P1, ui) — 21 SP
- **F5.1 Composer island** (P0, 5) — text input, send, length counter, disabled
  on block/spam, island isolation.
- **F5.2 Attachments** (P0, 5) — file picker, chips, client pre-validation
  mirroring config, remove.
- **F5.3 Optimistic send + reconcile** (P0, 3) — nonce match, sending/sent/failed
  + retry.
- **F5.4 Reply mode** (P1, 3) — quoted preview from `reply_to_id`, dismiss.
- **F5.5 Enter-behaviour setting + popover** (P1, 3) — newline/send toggle,
  persisted; keyboard shortcuts.
- **F5.6 Emoji + formatting toolbar** (P1, 2) — emoji picker (lazy), plain-text +
  safe markdown render.

### E6 — Realtime, presence & typing (P1, ui+domain) — 26 SP
- **F6.1 Echo/Reverb wiring** (P1, 5) — Echo bootstrap, capability flag,
  subscribe to `message.sent`.
- **F6.2 Live new-message into thread** (P1, 5) — patch list, mark read if open,
  respect scroll position.
- **F6.3 Polling fallback** (P1, 3) — adaptive `.visible`, no poll+subscribe
  overlap.
- **F6.4 Presence + last-seen** (P1, 5) — presence channel, `PresenceResolver`
  consumption, local-time display.
- **F6.5 Typing indicator** (P1, 5) — client/whisper emit+listen, debounce/
  expire, list + thread surfaces.
- **F6.6 Connection state UI** (P1, 3) — reconnect/offline banners, queued sends.

### E7 — Read receipts & unread UX (P1, ui+domain) — 16 SP
- **F7.1 Derived read status** (P1, 5) — Sent/Read from `last_read_at`; render on
  last applicable message.
- **F7.2 Live read receipts** (P1, 3) — consume `ConversationReadBroadcast`.
- **F7.3 Unread divider** (P1, 5) — "new messages" line, restore place on open.
- **F7.4 Mark-as-read on view** (P1, 3) — on open/scroll-bottom; reset badge.

### E8 — Search, filters & saved messages (P1, ui+domain) — 21 SP
- **F8.1 Inbox search UI** (P1, 5) — toggle field, debounced, no-results state,
  consume `SearchInboxQuery`.
- **F8.2 Saved tab** (P1, 5) — `SavedPanel`, list from `saved()`, save/unsave.
- **F8.3 Filters integration** (P1, 3) — wire E3.4 to scopes incl. unread/spam.
- **F8.4 In-thread search + jump** (P2, 8) — `SearchMessagesQuery`, scroll-to,
  highlight.

### E9 — Filament integration (P1–P2, filament) — 21 SP
- **F9.1 Plugin scaffold** (P1, 5) — `MessengerPlugin`, cluster, nav, config.
- **F9.2 ChatPage** (P1, 3) — mount `<livewire:messenger/>` full-width.
- **F9.3 MessageReportResource** (P1, 5) — table/filters/infolist + moderation
  actions (spam/block/clear/resolve/open-in-chat) + triage migration.
- **F9.4 Conversation/Message read-only resources** (P2, 5) — audit views,
  relation manager, export transcript.
- **F9.5 Widgets** (P2, 3) — open reports, messages/day, active convs,
  blocked/spam, latest reports.

### E10 — Modern delight (P2, ui+domain) — 29 SP
- **F10.1 Reactions UI** (P2, 5) — picker, render, consume E1.8.
- **F10.2 Link unfurls** (P2, 8) — server fetch+cache, render, privacy guard.
- **F10.3 Persisted drafts** (P2, 3) — local-first per conversation, optional
  sync.
- **F10.4 In-app notifications/toasts** (P2, 5) — consume notifications/inbox
  signal; mute UI.
- **F10.5 Bubble theme mode** (P2, 5) — aligned/colored/grouped variant via
  tokens.
- **F10.6 Voice message attachment** (P3→P2 stretch, 3) — recorder + audio render.

### E11 — Responsive, a11y, RTL, i18n (P1, ui) — 16 SP
- **F11.1 Responsive breakpoints** (P1, 5) — 3-col→2-col→master-detail→single +
  bottom sheets.
- **F11.2 Accessibility** (P1, 5) — keyboard nav, ARIA `feed`/`log`/`listbox`,
  focus mgmt, non-color states.
- **F11.3 RTL** (P1, 3) — full mirroring + icon flips.
- **F11.4 i18n** (P1, 3) — `messenger::ui.*` strings, ar + en defaults.

### E12 — Testing, docs, packaging, CI (P0–P2, all) — 21 SP
- **F12.1 Domain tests for new features** (P0, 5) — Pest, parity with existing
  style; SQLite+MySQL.
- **F12.2 UI component tests** (P1, 5) — Livewire test harness, interaction +
  realtime/poll paths.
- **F12.3 Filament tests** (P2, 3).
- **F12.4 Perf budget tests** (P1, 3) — long-thread scroll, N+1 guard, payload
  size.
- **F12.5 Docs & demo** (P1, 5) — install/usage/theming/realtime guides, demo
  app, screenshots.

### E13 — Advanced/optional (P3) — sized later
- Edit/delete/unsend (domain RFC: immutability vs. tombstones), pinned messages,
  forward/share, slash/canned replies (operator), multi-device sync polish.

---

## 4. Suggested sprint sequencing

| Sprint | Focus | Epics |
|--------|-------|-------|
| 1 | Foundations | E2, E1 (F1.1/1.2/1.10), E12 (F12.1) |
| 2 | Read path | E3, E4 (F4.1–4.3) |
| 3 | Send path | E5, E4 (F4.4–4.7) |
| 4 | Realtime trio | E6, E7 |
| 5 | Search/saved/filters + a11y/RTL | E8 (P1), E11 |
| 6 | Filament v1 | E9 (P1), E12 (F12.2/12.4/12.5) |
| 7+ | Delight | E10, E8.4, E9 (P2), E12 (F12.3) |

---

## 5. Proposed GitHub issues

> One issue per Feature (epics become **milestones/labels**). Labels:
> `area:domain` `area:ui` `area:filament`, `priority:P0…P3`, `type:feature`
> `type:chore` `type:test`. **These are proposed — not yet created** (see note
> below). Format ready to paste.

**Milestone: P0 — Foundations**
1. `[P0][domain] Add unread & only_spam inbox scopes` — F1.1
2. `[P0][domain] ParticipantPresenter contract + default` — F1.2
3. `[P0][domain] Backward-compat contract snapshot test` — F1.10
4. `[P0][ui] UI package scaffold (provider, config, Vite, publish tags)` — F2.1
5. `[P0][ui] Theme tokens + Tailwind preset (flat & bubble, dark-ready)` — F2.2
6. `[P0][ui] 3-column responsive shell + slots` — F2.3
7. `[P0][ui] Resolver wiring (presenter/presence/search) + defaults` — F2.4
8. `[P0][ui] channels.php + default channel auth` — F2.5
9. `[P0][ui] Base Blade components (avatar/badge/counter/star/timestamp)` — F2.6
10. `[P0][ui] Sidebar + ConversationList (lazy, computed)` — F3.1
11. `[P0][ui] ConversationListItem` — F3.2
12. `[P0][ui] Inbox empty state` — F3.3
13. `[P0][ui] Thread island + header + tabs` — F4.1
14. `[P0][ui] MessageList + MessageRow (flat, attachments, reply preview)` — F4.2
15. `[P0][ui] Infinite scroll older (keyset + offset preservation)` — F4.3
16. `[P0][ui] Composer (text, length, disabled states)` — F5.1
17. `[P0][ui] Composer attachments + client pre-validation` — F5.2
18. `[P0][ui] Optimistic send + reconcile + retry` — F5.3
19. `[P0][test] Domain tests for new P0 features` — F12.1

**Milestone: P1 — Parity & realtime**
20. `[P1][domain] Saved messages (table, actions, query, events)` — F1.3
21. `[P1][domain] SearchInboxQuery + ParticipantSearchResolver` — F1.4
22. `[P1][domain] PresenceResolver contract + presence-channel default` — F1.5
23. `[P1][domain] ConversationReadBroadcast (listener + event)` — F1.6
24. `[P1][domain] Inbox-activity broadcast (messenger.user.{id})` — F1.7
25. `[P1][ui] Filter dropdown (All/Unread/Starred/Archived/Spam)` — F3.4
26. `[P1][ui] Inbox realtime/poll updates` — F3.5
27. `[P1][ui] Star/Archive from list` — F3.6
28. `[P1][ui] Catch-up (after_id) + scroll-to-bottom FAB with count` — F4.4
29. `[P1][ui] Date separators & grouping` — F4.5
30. `[P1][ui] Message actions menu (reply/save/spambox/report)` — F4.6
31. `[P1][ui] Conversation actions menu (mark unread/archive/clear)` — F4.7
32. `[P1][ui] Reply mode (quoted preview)` — F5.4
33. `[P1][ui] Enter-behaviour setting + popover` — F5.5
34. `[P1][ui] Emoji + formatting toolbar` — F5.6
35. `[P1][ui] Echo/Reverb wiring + capability flag` — F6.1
36. `[P1][ui] Live new-message into thread` — F6.2
37. `[P1][ui] Polling fallback (adaptive, .visible)` — F6.3
38. `[P1][ui] Presence + last-seen` — F6.4
39. `[P1][ui] Typing indicator` — F6.5
40. `[P1][ui] Connection-state UI + queued sends` — F6.6
41. `[P1][ui] Derived read status (Sent/Read)` — F7.1
42. `[P1][ui] Live read receipts` — F7.2
43. `[P1][ui] Unread divider` — F7.3
44. `[P1][ui] Mark-as-read on view` — F7.4
45. `[P1][ui] Inbox search UI` — F8.1
46. `[P1][ui] Saved tab` — F8.2
47. `[P1][ui] Filters integration` — F8.3
48. `[P1][filament] Plugin scaffold + Messaging cluster` — F9.1
49. `[P1][filament] ChatPage (mount messenger UI)` — F9.2
50. `[P1][filament] MessageReportResource + moderation actions + triage migration` — F9.3
51. `[P1][ui] Responsive breakpoints + bottom sheets` — F11.1
52. `[P1][ui] Accessibility pass (keyboard/ARIA/focus/non-color)` — F11.2
53. `[P1][ui] RTL mirroring` — F11.3
54. `[P1][ui] i18n (ar/en strings)` — F11.4
55. `[P1][test] UI component tests` — F12.2
56. `[P1][test] Perf budget tests` — F12.4
57. `[P1][docs] Docs, theming/realtime guides, demo app` — F12.5

**Milestone: P2 — Delight**
58. `[P2][domain] Reactions (table, actions, query, events)` — F1.8
59. `[P2][domain] Notifications (opt-in NotifyRecipient + notification + mute)` — F1.9
60. `[P2][domain] Message-body fulltext search + optional index` — F1.4(P2)
61. `[P2][ui] In-thread search + jump-to-message` — F8.4
62. `[P2][ui] Reactions UI` — F10.1
63. `[P2][ui] Link unfurls` — F10.2
64. `[P2][ui] Persisted drafts` — F10.3
65. `[P2][ui] In-app notifications/toasts + mute UI` — F10.4
66. `[P2][ui] Bubble theme mode` — F10.5
67. `[P2][filament] Conversation/Message read-only resources + transcript export` — F9.4
68. `[P2][filament] Dashboard widgets` — F9.5
69. `[P2][test] Filament tests` — F12.3

**Milestone: P3 — Advanced (RFC-gated)**
70. `[P3][rfc] Message edit/delete/unsend vs. immutability (tombstones)` — E13
71. `[P3] Pinned messages` · 72. `[P3] Forward/share` · 73. `[P3] Voice messages`
· 74. `[P3] Slash/canned replies (operator)`

---

## 6. Risk register

| # | Risk | Likelihood | Impact | Mitigation |
|---|------|:---:|:---:|-----------|
| R1 | Reverb/WebSocket infra adds ops burden; clients without it | Med | High | Polling-first; realtime additive + capability-gated; document Reverb deploy. |
| R2 | Scroll/pagination jank on long threads | Med | High | Keyset (no OFFSET) + offset preservation + bounded DOM + stable keys; perf tests. |
| R3 | Host identity/presence/timezone assumptions leak into package | Med | Med | Contracts (`ParticipantPresenter`, `PresenceResolver`, `ParticipantSearchResolver`) with safe defaults; never read host columns. |
| R4 | Accidental BC break in domain | Low | High | Additive-only rule; contract snapshot test; never edit existing migrations/events. |
| R5 | "Delete"/"spambox" semantics mismatch user expectation | Med | Med | Rename to "Clear chat"; document; product sign-off (open decision #1/#2). |
| R6 | Immutability vs. edit/delete demand | Med | Med | Defer to P3 behind a domain RFC; design tombstones ahead. |
| R7 | Filament version churn couples UI to admin | Med | Med | Isolate in Filament package; UI is panel-agnostic; matrix test. |
| R8 | Livewire 4 newness / island/Volt API drift | Med | Med | Pin versions; thin abstractions; component tests; fallback patterns documented. |
| R9 | Optimistic send/reconcile race & duplicates | Med | Med | Client nonce matching; idempotent reconcile; dedupe on broadcast. |
| R10 | Attachment security (metadata-only validation) | Med | High | Document baseline; provide a content-inspection `SendPipe` recipe; host policy guidance. |
| R11 | RTL/a11y treated as afterthought | Med | Med | Acceptance gates per story; ar+en from day one. |
| R12 | Scope creep (reactions/unfurls/voice in v1) | High | Med | Strict P0/P1 cut line; delight features behind P2+ milestones. |
| R13 | Search scaling (LIKE on large message tables) | Low | Med | Optional fulltext index; name search via host resolver; paginate. |
| R14 | Cross-device / multi-tab consistency | Med | Med | `messenger.user.{id}` inbox signal + read sync; document trade-offs. |

## 7. Effort & sizing summary

- **P0 (sellable read+send, no realtime):** ~78 SP ≈ **~4–5 weeks**.
- **P1 (parity + realtime + Filament v1 + a11y/RTL):** ~120 SP ≈ **~6–7 weeks**.
- **P2 (delight):** ~58 SP ≈ **~3–4 weeks**.
- **P0–P2 total:** ~256–281 SP ≈ **~10–14 engineer-weeks** (single strong
  engineer; less with 2–3 parallelising across the three packages).
- **P3:** sized after RFCs.

Confidence: **High** for P0/P1 (mostly additive over a mature domain), **Medium**
for P2 (unfurls/reactions have unknowns), **Low/deferred** for P3 (needs domain
decisions).

---

### Note on creating the issues

The issues in §5 are **proposed and ready to create** but have **not** been
pushed to GitHub, to avoid spamming the repo before the plan is reviewed. On
approval I can create them as milestones + labels + issues (with sub-issue links
Epic→Feature) in one batch.
</content>
