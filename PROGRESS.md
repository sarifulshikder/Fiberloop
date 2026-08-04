# PROGRESS.md — Fiberloop Build Status

> Update this file every time you complete a task or a phase. The next agent session reads this FIRST to know where things stand. Keep entries short — this is a status board, not a diary.

Last updated: 2026-08-04
Current phase: Phase 1

## Phase Status
| Phase | Status | Notes |
|---|---|---|
| 0 — Foundation & Environment | Done | All tasks completed, verified working |
| 1 — Database Architecture | Done | CHECK constraints, factories, seeders, schema.md complete. migrate:fresh --seed verified |
| 2 — Auth & RBAC | Not started | |
| 3 — Customer/Subscriber Management | Not started | |
| 4 — Package & Pricing | Not started | |
| 5 — Billing & Invoicing Engine | Not started | |
| 6 — Payment Gateways | Not started | |
| 7 — FreeRADIUS Integration | Not started | |
| 8 — Network Device Management | Not started | |
| 9 — Reseller/Franchise Management | Not started | |
| 10 — Ticketing & Field Ops | Not started | |
| 11 — Notifications | Not started | |
| 12 — Filament Admin & Reports | Not started | |
| 13 — AI & Analytics | Not started | |
| 14 — Customer Portal & App | Not started | |
| 15 — Inventory & Assets | Not started | |
| 16 — Security & Data Hardening | Not started | |
| 17 — Testing & QA | Not started | |
| 18 — DevOps & Deployment | Not started | |
| 19 — Production Launch | Not started | |

Status values: `Not started` / `In progress` / `Blocked` / `Done`

## Open Questions
<!-- Log anything you had to guess at or need a human decision on -->
- (none yet)

## Known Issues / Tech Debt
<!-- Anything shipped imperfectly on purpose to keep moving -->
- (none yet)

## Key Decisions Log
<!-- One line per decision, e.g. "Phase 0: multi-tenancy (stancl/tenancy) deferred, tenant_id columns kept in schema" -->
- Pre-Phase-0 (Aug 2026): AGENTS.md/ROADMAP.md versions audited against Packagist/official docs. Target bumped to PHP 8.4+ (Laravel 13.3+ needs it via Symfony 8; `spatie/laravel-activitylog` v5 requires it outright). PostgreSQL target bumped 16→18 (current stable). FreeRADIUS pinned to 3.2.x (3.2.10). Octane driver switched Swoole→FrankenPHP (now Octane's default). Re-verify all of this again before Phase 0 install — it will be months old by the time you read it.
- Phase 0: Multi-tenancy enabled via stancl/tenancy v3.10.0 with PostgreSQL database manager (separate DB per tenant). Redis tenancy bootstrapper enabled.
- Phase 0: Pest PHP v5.0.3 with pest-plugin-laravel v5.0.1 adopted (supports Laravel 13.23+). PHPUnit kept as dev dependency for compatibility.
- Phase 0: Laravel Pint configured with PSR-12 preset + ordered_imports and no_unused_imports rules. Pre-commit/CI integration via composer scripts.
- Phase 1: Removed HasUuids trait from all models to prevent UUID/primary-key conflicts. Models now manually generate UUIDs for separate uuid columns while keeping bigint primary keys per AGENTS.md spec.
