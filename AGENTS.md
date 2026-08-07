# AGENTS.md — Fiberloop

> Read this file first, every session. It is the single source of truth for how to work on this repository. The detailed phase-by-phase build plan lives in `ROADMAP.md`. Current build status lives in `PROGRESS.md`. Read both before writing code.

## What Fiberloop Is
Fiberloop is an AI-assisted ISP billing and subscriber management platform for a fiber/FTTH internet provider, built to run at 100,000+ subscriber scale. It manages customers, packages, invoicing, payments, RADIUS-based network authentication, reseller hierarchies, support tickets, and network device monitoring, with an admin panel, a customer self-service app, and AI-assisted analytics.

## Tech Stack (do not substitute without explicit human approval)
| Layer | Choice |
|---|---|
| Backend framework | Laravel 13 (PHP 8.4+ — see PHP version note below) |
| Admin panel | Filament v5 |
| Database | PostgreSQL 18 — used for the app AND for FreeRADIUS (via `rlm_sql_postgresql`). Do not introduce MySQL. |
| Cache/Queue broker | Redis |
| Queue runtime | Laravel Horizon |
| App server | Laravel Octane (FrankenPHP — now Octane's default; Swoole remains a documented fallback) |
| Realtime | Laravel Reverb |
| Network AAA | FreeRADIUS 3.2.x (3.2.10 at time of writing) |
| Network hardware API | MikroTik RouterOS API, SNMP for OLT/switches |
| Customer app | Flutter |
| AI | Laravel AI SDK for in-app AI features; a separate Python/FastAPI microservice only for classic ML (churn/forecasting) |
| Testing | Pest |
| Infra | Docker, Nginx, GitHub Actions |

> **PHP version:** Laravel 13.0 nominally supports PHP 8.3+, but 13.3+ pulls in Symfony 8 components that effectively require PHP 8.4, and `spatie/laravel-activitylog` v5 (mandated below for financial audit logging) requires PHP 8.4 outright. **Target PHP 8.4+ from day one** to avoid a forced runtime upgrade mid-project.
>
> **Versions move fast in this ecosystem.** Every version above was checked against Packagist/official docs in August 2026. Before installing anything in Phase 0, run `composer show <package> --all` or check Packagist for that package's current `require.php` and `require.laravel/framework` constraints — do not assume this table is still accurate by the time you build. Log any version you had to change from this table in `PROGRESS.md`'s Key Decisions Log.

## Non-Negotiable Architecture Decisions
1. **One database engine.** PostgreSQL only, including the FreeRADIUS schema. Never install MySQL/MariaDB "because a tutorial used it."
2. **Money is integers.** Store all currency amounts in the smallest unit (poysha, i.e. BDT × 100) as `bigint`. Never `float`/`double` for money.
3. **Multi-tenancy from day one**, even if only one ISP uses it initially — every tenant-scoped table gets a `tenant_id` column and a global scope. Retrofitting this later is far more expensive than building it in now.
4. **Soft deletes** on customer, invoice, subscription, and payment tables — this is financial data, never hard-delete it.
5. **All money-moving and network-provisioning actions run as queued jobs**, not inline in HTTP requests — billing runs, RADIUS sync, SMS sends.
6. **RBAC via `spatie/laravel-permission`**, roles: `super_admin`, `admin`, `noc_engineer`, `support_agent`, `billing_agent`, `reseller`, `field_technician`, `customer`.
7. **Every financial mutation is written to `spatie/laravel-activitylog`.** No exceptions.

## Workflow Rules
- Work **one phase of `ROADMAP.md` at a time**, in order, unless the human explicitly says to skip ahead.
- Before starting a phase: re-read that phase's section in `ROADMAP.md` in full, and read `PROGRESS.md` to confirm what's already done.
- Plan → confirm the approach in a few bullet points → implement in small commits → run tests → update `PROGRESS.md` → only then move to the next task.
- Do not check off a phase's Definition of Done unless every item is actually verified (migrations run clean, tests pass, feature manually exercised) — not assumed.
- If a task is ambiguous, or you'd be inventing business logic that isn't specified (an exact late-fee percentage, an exact commission split), **stop and ask** rather than guessing. Log the open question in `PROGRESS.md` and use a clearly-labeled placeholder so work isn't blocked.
- Never invent a new table/column/folder naming convention that conflicts with what's already used elsewhere in the repo — grep first.
- **Browser-verifiable progress, every phase.** The project owner wants to open `/admin` in a real browser and see each phase's work with their own eyes, in parallel with you working — not just read about it in `PROGRESS.md`. Every phase's Definition of Done must include at least one item checkable by clicking around in the browser (a new Filament resource, a new dashboard widget, a visibly different screen). If a phase is genuinely backend-only with nothing new to click (e.g. a pure schema migration), say so explicitly in `PROGRESS.md` and name the next phase that will make it visible.

## Permission Boundaries
- **Safe, do without asking:** creating/editing files, running migrations in local/dev, running tests, installing packages already named in `ROADMAP.md`.
- **Ask first:** adding a package not listed in `ROADMAP.md`, editing an already-applied migration instead of writing a new one, anything touching production credentials, deleting data, or changing the tech stack table above.

## Commands
```bash
composer install
php artisan migrate
php artisan db:seed
composer test              # Pest
php artisan octane:start
npm run dev
```

## Code Conventions
- PSR-12, formatted with Laravel Pint (`vendor/bin/pint`) before every commit.
- Form Requests for validation — not inline `$request->validate()` in controllers.
- Actions/Service classes for business logic; keep controllers and Filament resources thin.
- Every migration must have a working `down()`.
- English for all code, comments, and commit messages (the team may speak Bengali day to day; code stays English for tooling compatibility).

## Verification Reality
All artisan/test commands must run via `docker exec -it fiberloop-app <command>`, never bare. A command run outside the container failing does not mean "no access."

**Standing Rule:** Any message claiming something is "done," "fixed," "verified," "audited," or "confirmed" must include the actual raw output or file content proving it in that same message — a prose description of what you did is not sufficient and will be rejected.

## When Stuck
Ask a specific question rather than guessing silently. It's cheaper to pause for one answer than to build the wrong thing for three phases.
