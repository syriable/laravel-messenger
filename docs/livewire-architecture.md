# Team C — Livewire 4 Architecture

> The presentation layer is built on **Livewire 4** (single-file/Volt-capable
> components, islands, `wire:navigate`, lazy components, `#[Computed]` caching,
> `wire:poll`, Echo integration). It consumes the package's public API
> (`Messenger::*`, Queries, Actions) — never the DB directly — and ships as a
> separate, panel-agnostic UI package that the host (or the Filament plugin)
> mounts.

## 1. Package boundary

```
syriable/laravel-messenger            (existing — headless domain, unchanged)
syriable/laravel-messenger-ui         (NEW — Livewire 4 components + assets + theme)
syriable/laravel-messenger-filament   (NEW — Filament plugin, thin; see Team D)
```

Splitting the UI into its own composer package keeps the domain package
dependency-free (no Livewire dependency leaks into headless installs) and lets
the UI be versioned independently. The UI package `require`s the domain package.

## 2. Component tree

```
Messenger (full-page route component / Filament Page)        [stateful, URL-bound]
│  props: ?conversationId, scope, search
│
├── Sidebar (island)
│   ├── ListHeader        (filter dropdown + search toggle)   [emits scope/search]
│   ├── ConversationList  (#[Lazy])                            [computed: conversations]
│   │   └── ConversationListItem (×N, stateless Blade/island)
│   └── InboxEmptyState
│
├── Thread (island, keyed by conversationId)                 [stateful]
│   ├── ThreadHeader      (presence, actions menu, star, tabs)
│   ├── SafetyBanner      (slot)
│   ├── MessageList       (#[Lazy], infinite scroll)           [computed: messages page]
│   │   ├── DateSeparator
│   │   └── MessageRow (×N)  ──► MessageActionsMenu (Reply/Save/Spam/Report)
│   ├── TypingIndicator   (Echo-driven, ephemeral)
│   ├── ScrollToBottomFab
│   └── Composer          (island)                             [own state: draft, attachments]
│       └── ComposerToolbar / EnterBehaviourPopover
│
├── SavedPanel (island, lazy)        — "Saved" tab content
│
└── ProfilePanel (island, lazy)      — identity + host slot
    └── ProfileEmpty / hidden
```

**Why islands?** Livewire 4 islands let the Composer re-render on every keystroke
**without** re-rendering the (expensive) MessageList, and let a new inbound
message patch the MessageList **without** touching the Composer draft. This is
the single most important performance decision for chat.

## 3. Components vs. pages vs. Volt

| Unit | Type | Rationale |
|------|------|-----------|
| `Messenger` | **Full-page class component** (route + Filament Page) | Owns URL state (`#[Url] $conversationId, $scope, $q`), coordinates children, mounts layout. Class-based for clarity/testability. |
| `Sidebar`, `Thread`, `Composer`, `ProfilePanel`, `SavedPanel` | **Class components / islands** | Independent render boundaries; heavier logic. |
| `ConversationListItem`, `MessageRow`, `DateSeparator`, badges | **Stateless Blade components** | Pure presentation; no Livewire overhead per row. |
| Small interactive bits (menus, popovers, toggles) | **Volt (functional)** *optional* | Where a tiny self-contained component reads better as Volt. **Guideline:** Volt for ≤~40-line leaf interactivity, class components for anything with multiple computeds/listeners. |

> **Volt usage decision:** Volt is *allowed but not required*. Default to class
> components for the five stateful units (testability, IDE support); use Volt
> only for leaf menus/toggles where it reduces ceremony. Keep one style per file
> type consistently.

## 4. State management

- **URL is the source of truth for navigation** — `#[Url] public ?string
  $conversationId; #[Url] public string $scope = 'all'; #[Url] public string $q
  = '';` on `Messenger`. Deep-linkable, back-button friendly, `wire:navigate`
  for instant nav between conversations.
- **Server-authoritative data via `#[Computed]`** — `conversations()`,
  `messages()`, `conversation()`, `otherParticipant()` are cached computeds that
  call the package Queries. Cache is busted by Livewire events (see §5) so a new
  message recomputes only the affected island.
- **Local UI state stays local** — composer draft, attachment list, open menu,
  scroll position live on the child island and never round-trip globally.
- **No global store** — Livewire events + URL params replace a Redux-style
  store. Cross-island coordination is event-based.
- **Optimistic UI** — Composer appends a temporary message row (client-side
  via Alpine) immediately, then reconciles when the server `messageSent` event
  returns the persisted row (matched by a client nonce).

## 5. Events (intra-app, Livewire)

| Event | Emitter → Listener | Effect |
|-------|--------------------|--------|
| `conversation-selected {id}` | Sidebar → Messenger | sets `$conversationId`, `wire:navigate` |
| `message-sent {conversationId}` | Composer → Thread, Sidebar | Thread appends + scrolls; Sidebar re-sorts + updates snippet |
| `conversation-read {id}` | Thread → Sidebar | clears unread badge |
| `scope-changed {scope}` / `search-changed {q}` | ListHeader → Sidebar | recompute list |
| `reply-requested {messageId}` | MessageRow → Composer | enter reply mode |
| `message-saved {messageId}` | MessageRow → SavedPanel | refresh saved list |
| `conversation-action {type,id}` | menus → Messenger | archive/clear/spam/markUnread, then refresh |

These are Livewire `dispatch()`/`#[On]` events between islands. They map
1:1 onto the package's domain events conceptually but are UI-scoped.

## 6. Realtime strategy

**Transport:** Laravel Echo + **Reverb** (default; Pusher-compatible). The
package already emits `MessageSentBroadcast` on
`messenger.conversation.{id}` (private by default) with event alias
`message.sent`.

Three channels per concern:

| Channel | Type | Purpose |
|---------|------|---------|
| `messenger.conversation.{id}` | private | new messages (`message.sent`), read receipts (`conversation.read` — new), typing (client/whisper `typing`) |
| `messenger.user.{participant}` | private | inbox-level signals: new conversation, global unread changes, cross-device sync |
| `messenger.presence.{scope}` | **presence** | who's online; backs the `PresenceResolver` default |

**Echo wiring in Livewire 4:** use `#[On('echo-private:messenger.conversation.
{conversationId},.message.sent')]` listeners on the `Thread`/`Sidebar` islands.
On receipt:
- **Thread (open conversation):** if `event.sender != self`, fetch/patch the new
  message into `MessageList` and mark read; respect "don't auto-scroll if user
  scrolled up" (increment the ScrollToBottomFab badge instead).
- **Sidebar:** bump the conversation to the top, update snippet/time/unread for
  any conversation, not just the open one.

**Typing & read receipts** ride the same private channel via lightweight client
events (typing) and a new broadcast (`ConversationReadBroadcast`, see Team E).
Attachments are **not** in the broadcast payload (documented); the Thread
re-loads the message when `has_attachments` is true.

## 7. Polling strategy (graceful degradation)

Realtime is **optional**. When `messenger.broadcasting.enabled = false` (or Echo
isn't configured), fall back to polling — the UI must work either way:

- **Sidebar:** `wire:poll.visible.15s` recompute of the inbox (cheap: indexed
  `last_message_at` sort + `unread_count` read). `.visible` so backgrounded tabs
  don't poll.
- **Thread (open):** `wire:poll.visible.5s` for `after_id` catch-up of new
  messages only (keyset, no full reload).
- **Adaptive:** poll faster (3–5s) while the tab is focused & active, slow
  (30s) when idle; pause entirely when a working Echo connection is detected.
- A single `realtime` capability flag drives whether poll directives are
  rendered at all (don't poll *and* subscribe).

## 8. Broadcasting strategy

- Reuse the package's `MessageSentBroadcast` as-is.
- **New broadcasts (in UI/domain packages):** `ConversationReadBroadcast`
  (read receipts), and a thin **inbox** broadcast on `messenger.user.{id}` so
  the Sidebar updates without polling. Typing is a **client event** (no server
  broadcast class) to keep it cheap and serverless.
- **Authorization:** ship `routes/channels.php` registrations gating
  `messenger.conversation.{id}` to its two participants and
  `messenger.user.{id}` to that user. The host maps participant identity → auth
  (we provide a default using the authenticated user as the participant).
- Keep payloads scalar/small; the DB is authoritative (per package design).

## 9. Lazy loading

- **`#[Lazy]` components:** `Thread`, `MessageList`, `ProfilePanel`, `SavedPanel`
  render a skeleton placeholder first, then hydrate — so opening the page is
  instant and the heavy message query runs out-of-band.
- **Deferred profile/saved:** only load when their tab/panel is visible.
- **`wire:navigate`** between conversations reuses the persistent shell, swapping
  only the `Thread` island.
- **Asset strategy:** ship compiled, versioned CSS/JS via the package; lazy-load
  emoji picker and any heavy editor on first use.

## 10. Infinite scroll (messages)

The package **already** supports keyset cursor pagination — `messages($conv,
$participant, ['limit' => N, 'before_id' => $oldestLoadedId])`. We exploit it:

- Initial load: newest `N` (e.g., 30) messages, bottom-anchored.
- **Scroll-up:** an IntersectionObserver sentinel at the top triggers
  `loadOlder()` → query with `before_id = firstLoadedMessageId`, prepend, and
  **preserve scroll offset** (measure height before/after, adjust `scrollTop`).
- **Scroll-down / catch-up:** `loadNewer()` with `after_id = lastLoadedMessageId`
  on realtime/poll events.
- Maintain a bounded in-memory window (e.g., cap at a few hundred rows; drop the
  far end) to keep the DOM light on long threads.
- Use stable keys (`wire:key="msg-{ulid}"`) so Livewire morphs rather than
  re-creates rows — essential for scroll stability and avoiding flicker.

## 11. Performance checklist

- Islands isolate Composer keystrokes from MessageList. ✅
- `#[Computed]` memoizes per-request query results; events bust precisely. ✅
- Stateless Blade rows (no per-row Livewire component). ✅
- Keyset pagination (no `OFFSET`), bounded DOM window. ✅
- `.visible` polling, Echo-preferred, adaptive intervals. ✅
- Lazy + `wire:navigate` shell reuse → minimal payloads. ✅
- Eager-loading handled by the package queries (no N+1). ✅
- Debounced search (`wire:model.live.debounce.300ms`) and typing. ✅

## 12. Accessibility & i18n

- Full keyboard nav (arrow keys in list, Enter to open, composer shortcuts per
  the Enter-behaviour setting), focus management on open/close menus, ARIA roles
  (`log`/`feed` for the message stream, `listbox` for the list).
- **RTL support is first-class** (the package owner's locale is `ar`): mirror the
  3-column layout, message alignment, and icons under `dir="rtl"`. All strings
  via Laravel translation files (`messenger::ui.*`).
</content>
