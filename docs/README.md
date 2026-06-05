# Laravel Messenger — Full-Stack v-Next Planning Suite

This directory contains the complete **analysis & planning** deliverables for
evolving `syriable/laravel-messenger` from a headless backend domain into a
**full-stack, sellable** messaging solution (Livewire 4 UI + Filament
integration, Laravel 12+).

> **No code has been written yet.** Per the brief, this is the analysis and
> architecture phase. Implementation begins only after the plan is reviewed and
> the open product decisions (master plan §12) are signed off.

## Reading order

| Phase | Document | Owner team |
|-------|----------|-----------|
| Fan-out | [`ui-analysis.md`](./ui-analysis.md) | A — UI Reverse Engineering |
| Fan-out | [`product-specification.md`](./product-specification.md) | B — Product Architect |
| Fan-out | [`livewire-architecture.md`](./livewire-architecture.md) | C — Livewire 4 |
| Fan-out | [`filament-integration.md`](./filament-integration.md) | D — Filament |
| Fan-out | [`package-integration.md`](./package-integration.md) | E — Package Architecture |
| Fan-out | [`ux-review.md`](./ux-review.md) | F — UX Review |
| **Fan-in** | [`master-implementation-plan.md`](./master-implementation-plan.md) | Consolidated |
| Planning | [`backlog.md`](./backlog.md) | Epics/Features/Stories + proposed GitHub issues + risks/estimates |
| **Usage** | [`full-stack-usage.md`](./full-stack-usage.md) | **How to install & use the implemented UI + Filament + new features** |
| Reference | [`ARCHITECTURE.md`](./ARCHITECTURE.md) | Existing domain architecture (unchanged) |

## TL;DR

- The existing domain package already covers **~90%** of the backend a
  Fiverr-style inbox needs. Work is **mostly additive + front-of-house**.
- **Three packages:** `…-messenger` (domain, headless, unchanged public API) ·
  `…-messenger-ui` (Livewire 4 + theme + realtime) · `…-messenger-filament`
  (thin plugin: mounts the UI + moderation admin).
- **Extend by composition, never fork.** New domain features (saved messages,
  search, inbox scopes, presence/typing contracts, read/inbox broadcasts,
  notifications, reactions) are **additive and backward-compatible**.
- **Realtime is optional:** Reverb/Echo preferred, adaptive polling fallback;
  the UI works either way.
- **Sellable:** neutral themeable tokens (flat **and** bubble modes), slots, RTL
  + a11y from day one — not a hard-coded Fiverr clone.
- **Estimate:** P0–P2 ≈ **~10–14 engineer-weeks**; a sellable P0 (read+send,
  no realtime) ≈ **~4–5 weeks**.

## Open product decisions (need sign-off)

See `master-implementation-plan.md` §12 — notably: "Delete" → "Clear chat"
semantics, per-message "spambox" → conversation spam, default neutral theme +
dual message styles, Reverb as default transport, saved-messages domain
placement, notifications off-by-default.
</content>
