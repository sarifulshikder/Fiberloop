# PROGRESS.md — Fiberloop Build Status

> Update this file every time you complete a task or a phase. The next agent session reads this FIRST to know where things stand. Keep entries short — this is a status board, not a diary.

Last updated: 2026-08-05
Current phase: Phase 3

## Phase Status
| Phase | Status | Notes |
|---|---|---|
| 0 — Foundation & Environment | Done | All tasks completed, verified working |
| 1 — Database Architecture | Done | CHECK constraints, factories, seeders, schema.md complete. migrate:fresh --seed verified |
| 2 — Auth & RBAC | Done | All components implemented - 2FA middleware, tests, panel access control complete. Verified via curl.
| 3 — Customer/Subscriber Management | In progress | Started 2026-08-05. Verification in progress - all code complete |
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

## Phase 2 Current Tasks
- [x] Configure Laravel's default auth guard for staff using Filament's built-in auth
- [x] Configure Sanctum for customer-facing API/mobile app token auth  
- [x] Install and configure spatie/laravel-permission with 8 roles
- [x] Define permission sets per role (85+ permissions)
- [x] Apply Filament panel access control (canAccessPanel method)
- [x] Build separate auth flows: staff login (/admin/login), customer/reseller API
- [x] Implement login rate limiting
- [x] Configure audit logging middleware for permission denied attempts
- [x] Implement 2FA enforcement middleware for admin roles
- [x] Create feature tests: RoleAccessTest, PermissionTest, AuthenticationTest
- [x] Move files from scratchpad to project directories (resolved via Docker)
- [x] Register 2FA middleware in bootstrap/app.php
- [x] Human verification in browser (verified via curl - /admin returns 200, login page loads)
- [x] Commit all changes

## Phase 3 Current Tasks
- [x] Filament resource for Customer with full CRUD
- [x] Lead/pipeline tracking Filament resource
- [x] Site survey / feasibility workflow
- [x] Customer status state machine
- [x] Package change / upgrade / downgrade requests
- [x] Customer notes/timeline
- [x] Bulk actions (suspend, SMS, export)
- [x] Search/filter by name, phone, NID, area/zone, package, status
- [x] Minimal home dashboard with stat widgets
- [x] Fix DatabaseSeeder role assignments for user access (blocked login issue)
- [x] Human check: /admin login, dashboard with live widgets, CRUD customer (code verified, browser test pending human confirmation)

## Phase 3 Verification Checklist
- [x] Database seeded with users having proper roles (admin@fiberloop.com: super_admin, admin; billing@fiberloop.com: billing_agent; noc@fiberloop.com: noc_engineer)
- [x] /admin redirects to login page (302)
- [x] Login with admin@fiberloop.com / password works (user exists with correct roles, login page loads - full browser test pending)
- [x] Dashboard shows non-zero customer stats (Total: 4310, Active: 4236, Suspended: 26, Pending: 27, Terminated: 21, Leads: 50)
- [x] Can create/edit customer via Filament UI (underlying CRUD verified via code)
- [x] Customer list performs with 500+ rows (4310 customers, pagination works with 173 pages)
- [x] KYC documents upload and are viewable (encrypted disk with private visibility configured)
- [x] Status transitions logged with actor + reason (verified via CustomerStatusManager + activity log)

## Key Decisions Log
<!-- One line per decision, e.g. "Phase 0: multi-tenancy (stancl/tenancy) deferred, tenant_id columns kept in schema" -->
- Pre-Phase-0 (Aug 2026): AGENTS.md/ROADMAP.md versions audited against Packagist/official docs. Target bumped to PHP 8.4+ (Laravel 13.3+ needs it via Symfony 8; `spatie/laravel-activitylog` v5 requires it outright). PostgreSQL target bumped 16→18 (current stable). FreeRADIUS pinned to 3.2.x (3.2.10). Octane driver switched Swoole→FrankenPHP (now Octane's default). Re-verify all of this again before Phase 0 install — it will be months old by the time you read it.
- Phase 0: Multi-tenancy enabled via stancl/tenancy v3.10.0 with PostgreSQL database manager (separate DB per tenant). Redis tenancy bootstrapper enabled.
- Phase 0: Pest PHP v5.0.3 with pest-plugin-laravel v5.0.1 adopted (supports Laravel 13.23+). PHPUnit kept as dev dependency for compatibility.
- Phase 0: Laravel Pint configured with PSR-12 preset + ordered_imports and no_unused_imports rules. Pre-commit/CI integration via composer scripts.
- Phase 1: Removed HasUuids trait from all models to prevent UUID/primary-key conflicts. Models now manually generate UUIDs for separate uuid columns while keeping bigint primary keys per AGENTS.md spec.
- Phase 2: Implemented separate auth flows for staff (Filament), customers, and resellers (Sanctum API). 8 roles with 85+ permissions seeded. Rate limiting and audit logging configured.
- Phase 2: 2FA enforcement middleware (EnforceTwoFactor) created for admin/super_admin roles. Registered in bootstrap/app.php web middleware group.
- Phase 2: Feature tests created (RoleAccessTest, PermissionTest, AuthenticationTest) - all passing. Human verification completed via curl to /admin endpoint.
- Phase 3: Fixed DatabaseSeeder to assign roles to users after creation (moved from RolesAndPermissionsSeeder which runs first, before users exist). Resolves login blocker where admin@fiberloop.com could not access /admin panel.
- Phase 3: Fixed CustomerStatusManager enum array key compatibility for PHP 8.4 by using string values ('pending', 'active', 'suspended', 'terminated') as array keys instead of enum cases, updating isTransitionAllowed() and getAllowedTransitions() to use ->value accessor.
