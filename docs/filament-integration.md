# Team D — Filament Integration

> Filament's role is **two distinct surfaces**: (1) an **end-user chat
> experience** mountable inside a Filament panel, and (2) **admin/moderation
> tooling** for operators. The hard rule below decides what belongs *inside*
> Filament vs. what stays in the panel-agnostic UI/domain packages.

## 1. The in/out boundary (decision)

| Concern | Inside Filament | Outside Filament (UI/domain pkg) | Why |
|---------|:---:|:---:|-----|
| End-user chat UI (3-pane messenger) | mount as a **Page** | **built here** (Livewire 4) | The chat must run with or without Filament (Breeze/Jetstream/custom apps). Filament only *hosts* it. |
| Moderation of reports | ✅ **Resource** | | Operator-only CRUD/triage — Filament's sweet spot. |
| Conversation/Message inspection | ✅ **read-only Resources** | | Support/audit views. |
| Messaging stats | ✅ **Widgets** | | Dashboard charts/counters. |
| Domain logic (send, block, clear…) | | ✅ **package Actions** | Filament Actions *call* package Actions; no business logic in Filament. |
| Realtime/presence/typing | | ✅ **UI package** | Transport concerns; Filament page reuses them. |
| Theme/tokens | | ✅ **UI package** | One theme, both contexts; Filament page inherits via CSS vars. |

**Principle:** Filament is a *delivery and operations* layer. All behaviour lives
in `syriable/laravel-messenger` (domain) and `…-ui` (presentation). The Filament
plugin is **thin glue**.

## 2. Plugin shape

`syriable/laravel-messenger-filament` exposes a `MessengerPlugin` implementing
`Filament\Contracts\Plugin`:

```
MessengerPlugin::make()
    ->chat(bool|Closure)            // register the end-user Chat page
    ->moderation(bool|Closure)      // register report/conversation resources
    ->widgets(bool|Closure)         // register dashboard widgets
    ->cluster(MessengerCluster::class)
    ->navigationGroup('Messaging')
```

Registered in a panel provider via `->plugin(MessengerPlugin::make())`.

## 3. Panels

- **Default:** integrate into the host's existing panel(s) — no dedicated panel
  required.
- **Optional dedicated panel** (`/messenger`) for apps that want a standalone
  messaging console (e.g., a support/agent desk). Provide a ready-made
  `MessengerPanelProvider` users can copy. Multi-panel safe: navigation,
  policies and tenancy scoping respect the host panel's context.
- **Tenancy:** if the host panel is multi-tenant, the participant resolver must
  scope to the tenant; document the hook (Team E `ParticipantResolver`).

## 4. Pages

| Page | Purpose |
|------|---------|
| `ChatPage` (full-page Livewire) | Hosts the `…-ui` `Messenger` root component full-width (`getMaxContentWidth() = Full`, hidden topbar padding). This is the operator/seller inbox **inside** Filament. Slug `/messages`. |
| `ModerationDashboard` (optional) | Landing page combining moderation widgets. |

`ChatPage` is essentially:
`class ChatPage extends Filament\Pages\Page { protected static string $view =
'messenger-filament::chat'; }` whose Blade renders `<livewire:messenger />`.
**No duplicated logic.**

## 5. Clusters

Group operator tooling under a single **`Messaging`** cluster so navigation stays
tidy:

```
Messaging (cluster)
├── Inbox            → ChatPage
├── Reports          → MessageReportResource
├── Conversations    → ConversationResource (read-only)
└── Messages         → MessageResource (read-only, filtered)
```

The cluster keeps the end-user Chat page and admin resources visually grouped
while letting hosts hide any leg via the plugin booleans.

## 6. Resources

All resources resolve models via `messenger.models.*` (so host subclasses work)
and are **read-mostly** — the domain is largely immutable.

### `MessageReportResource` (primary admin surface)
- **Table:** reporter, reported message (excerpt), reason, note, created_at;
  filters by reason, date range, "resolved" (host-added column — see Team E
  optional moderation status), conversation.
- **Actions (row/bulk):** *View context* (open the surrounding messages in a
  modal/relation), *Move conversation to spam* (calls `Messenger::spam`),
  *Block conversation* (`Messenger::block`), *Dismiss* / *Mark resolved* (host
  moderation status), *Open in Chat* (deep-link to `ChatPage`).
- **Infolist:** full message + attachments + both participants.
- Most valuable Filament surface — turns the package's `MessageReport` into a
  real moderation queue.

### `ConversationResource` (read-only)
- **Table:** participants (both sides), last_message_at, message count,
  blocked/spam flags, unread totals.
- **Filters:** has-reports, blocked, spam, date range, participant search.
- **Actions:** *View thread* (relation manager / modal, read-only), *Force
  clear*, *Block/Spam* (operator override), *Export transcript*.
- **Relation manager:** `MessagesRelationManager` (read-only message list).

### `MessageResource` (read-only, usually accessed via relations)
- For search/audit across all messages; heavy — gate behind permission and
  default to filtered (by conversation/date) to avoid full-table scans.

> **Do NOT** build a Filament "compose message" Resource form — sending is the
> end-user Chat page's job and goes through the send pipeline. Operator
> "message a user" is a deliberate, separate, audited action if needed.

## 7. Widgets

For the panel dashboard / `ModerationDashboard`:

| Widget | Type | Source |
|--------|------|--------|
| `OpenReportsStat` | Stat | count of unresolved `MessageReport` |
| `MessagesSentChart` | Chart (line) | messages/day |
| `ActiveConversationsStat` | Stat | conversations with activity in N days |
| `BlockedSpamStat` | Stat | participants blocking/spamming |
| `LatestReportsTable` | Table widget | newest reports with quick actions |

Widgets are read-only aggregates over indexed columns; cache with short TTL to
keep dashboards cheap.

## 8. Navigation

- Single **`Messaging`** navigation group/cluster.
- Badges: `Inbox` shows the operator's unread count
  (`Messenger::unreadCount()`); `Reports` shows the open-reports count
  (`getNavigationBadge()`), with red color when > 0.
- Respect Filament authorization: each resource/page gated by a policy/permission
  (`view_messenger_reports`, `access_messenger_chat`, …). The package stays
  headless; **Filament policies live in the Filament plugin/host**.

## 9. Tables & Forms (patterns)

- **Tables:** server-side pagination, indexed sortable columns
  (`last_message_at`, `created_at`), `ulid` shown truncated/copyable, eager-load
  via the package query objects where possible to avoid N+1, persistent filters.
- **Forms:** minimal — only for moderation metadata (resolution status/notes),
  never for composing domain messages. Reuse Filament validation; domain
  validation stays in the send pipeline.

## 10. Actions (Filament → domain mapping)

| Filament Action | Calls | Notes |
|-----------------|-------|-------|
| Move to spam | `Messenger::spam($conv,$operatorAsParticipant?)` | operator override; document participant context |
| Block | `Messenger::block(...)` | |
| Force clear | `Messenger::clear(...)` | |
| Mark report resolved | host moderation-status update | additive column, optional |
| Open in Chat | route to `ChatPage?conversation={id}` | |
| Export transcript | `Messenger::messages(...)` → CSV/PDF | read query |

Every Filament Action is a **thin wrapper** that calls a package Action/Query,
shows a notification, and refreshes — **zero business logic** in the Filament
layer.

## 11. Deliverable surface summary

- 1 plugin (`MessengerPlugin`) + 1 cluster (`Messaging`).
- 1–2 pages (`ChatPage`, optional `ModerationDashboard`).
- 3 resources (`MessageReport` rich; `Conversation` + `Message` read-only).
- ~5 widgets.
- A handful of thin Actions mapping to the domain API.
- **No domain logic, no theme, no realtime code** — all inherited.
</content>
