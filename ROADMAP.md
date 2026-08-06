# Fiberloop — Production Roadmap
### AI-Powered ISP Billing & Management Platform

**Stack:** Laravel 13 (PHP 8.4+) · PostgreSQL 18 · Filament v5 · FreeRADIUS 3.2.x · Redis · Laravel Octane (FrankenPHP) / Horizon / Reverb · Flutter · Laravel AI SDK

> Versions above were verified against Packagist/official docs in August 2026 — see the PHP version note in `AGENTS.md`. Re-check package constraints at Phase 0 before installing; this is a fast-moving ecosystem and this document will age.

---

## How to Use This Document

This is the complete, phase-by-phase build plan for Fiberloop — from an empty repository to a production-ready ISP billing and management platform. It is written for AI coding agents to execute autonomously, phase by phase.

**Rules:**
1. Read `AGENTS.md` before this file — it holds the non-negotiable architecture rules and workflow constraints.
2. Work through phases **in order**. Each phase lists its dependencies; do not start a phase before its dependencies are marked Done in `PROGRESS.md`.
3. Complete a phase's **entire** Definition of Done before moving to the next. Partial phases compound into a mess two or three phases later.
4. Update `PROGRESS.md` after every task, not just at the end of a phase.
5. Every phase below is written to be self-contained — you shouldn't need to re-read earlier phases in full to execute the current one. Dependencies and required context are stated explicitly.
6. If something in a phase conflicts with something already built, stop and log it in `PROGRESS.md` under "Open Questions" rather than silently overriding earlier work.
7. If a task requires a business decision that isn't specified here (an exact fee percentage, whether a feature is in scope, an exact regulatory format), do not guess and move on — log it as an Open Question and pick a clearly-labeled placeholder so work isn't blocked.

---

## Phase Index

- [x] Phase 0 — Foundation & Environment Setup
- [x] Phase 1 — Database Architecture & Domain Modeling
- [x] Phase 2 — Authentication & Multi-Role Access Control
- [x] Phase 3 — Customer / Subscriber Management (CRM)
- [x] Phase 4 — Package, Plan & Pricing Engine
- [x] Phase 5 — Billing & Invoicing Engine
- [x] Phase 6 — Payment Gateway Integration
- [x] Phase 7 — FreeRADIUS AAA Integration
- [x] Phase 8 — Network Device Management (MikroTik / OLT / NOC)
- [x] Phase 9 — Reseller / Franchise Management
- [ ] Phase 10 — Support Ticketing & Field Operations
- [ ] Phase 11 — Notifications (SMS / Email / Push)
- [ ] Phase 12 — Filament Admin Panel: Dashboards & Reports
- [ ] Phase 13 — AI & Analytics Layer
- [ ] Phase 14 — Customer Self-Service Portal & Mobile App
- [ ] Phase 15 — Inventory & Asset Management
- [ ] Phase 16 — Security & Data Hardening
- [ ] Phase 17 — Testing & QA
- [ ] Phase 18 — DevOps, CI/CD & Deployment
- [ ] Phase 19 — Production Launch Checklist

---

## Phase 0 — Foundation & Environment Setup

**Goal:** A running Laravel 13 app, connected to PostgreSQL and Redis, with the base folder structure, tooling, and conventions in place so every later phase builds on the same foundation.

**Depends on:** Nothing — this is the starting point.

### Tasks
1. Install Laravel 13 (`composer create-project laravel/laravel fiberloop`), confirm PHP 8.4+ (check `php -v` before starting — see the PHP version note in `AGENTS.md`).
2. Install and configure PostgreSQL 18; create the `fiberloop` database and a dedicated low-privilege app DB user.
3. Install Redis; wire up `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis` in `.env`.
4. Install Laravel Octane with the FrankenPHP driver (Octane's current default — embeds PHP directly, no separate PECL extension build to maintain). Use Swoole only if the team already has Swoole ops experience; if so, record that choice in `PROGRESS.md`.
5. Install and configure: `filament/filament` v5, `laravel/horizon`, `laravel/reverb`, `laravel/sanctum`, `spatie/laravel-permission`, `spatie/laravel-activitylog`, `spatie/laravel-backup`, `laravel/pint`, `pestphp/pest`.
6. Decide whether `stancl/tenancy` (multi-ISP SaaS) is a real near-term goal. Either way, record the decision in `PROGRESS.md` — do not half-implement it.
7. Establish the base folder convention inside `app/`: `app/Models`, `app/Actions` (business logic), `app/Services` (external integrations: RADIUS, MikroTik, payment gateways, SMS), `app/Filament` (panel resources), `app/Jobs`, `app/Enums`, `app/Http/Requests`.
8. Configure `.env.example` with every variable later phases will need — build this up as you go, don't let it go stale.
9. Set up the Git repo, `.gitignore`, a conventional commit style, and a `README.md` for human contributors (separate from `AGENTS.md`, which is for agents).
10. Configure Laravel Pint and add a pre-commit or CI step that runs it.
11. Set up Docker Compose for local dev: app, postgres, redis, and a freeradius stub (filled in properly in Phase 7).
12. Create `PROGRESS.md` from the template if it isn't already present, and keep it updated from this point forward.

### Definition of Done
- [ ] `php artisan serve` (or Octane) boots with no errors.
- [ ] DB connection verified with a working migration + rollback.
- [ ] Redis connection verified (cache and queue both tested with a trivial job).
- [ ] Filament admin panel installed and reachable at `/admin` with a default admin user seeded.
- [ ] Pint runs clean on a fresh checkout.
- [ ] `docker-compose up` brings up app + db + redis locally.
- [ ] `PROGRESS.md` reflects Phase 0 as Done.

---

## Phase 1 — Database Architecture & Domain Modeling

**Goal:** The core schema and Eloquent models for every domain in the system exist, migrated, and related correctly — even though most business logic is built in later phases. Getting this right early prevents painful schema churn later.

> Nothing new is visible in the browser at the end of this phase — it's pure schema/model work. That's expected. Phase 2 adds visible login screens, and Phase 3 is where the admin dashboard starts showing real, clickable data.

**Depends on:** Phase 0.

### Architecture Decisions (apply throughout the whole project, not just this phase)
- **Money:** every amount column is `bigint`, storing the smallest currency unit (poysha, i.e. BDT × 100). Never `decimal`/`float` for money.
- **Multi-tenant scoping:** every business table gets a `tenant_id` (nullable FK to a `tenants`/`isps` table) — even in a single-ISP deployment, this keeps Phase 9 (reseller/franchise) and any future multi-ISP SaaS work cheap instead of a rewrite.
- **Primary keys:** `bigint` auto-increment for internal FKs (performance); expose a separate `uuid` public-facing identifier on customer-facing models (customers, invoices) so sequential IDs are never leaked externally.
- **Soft deletes** on: customers, invoices, payments, subscriptions.
- **Timestamps + `created_by`/`updated_by`** on all financial tables.

### Core Entities to Model (migrations + Eloquent models + factories)
1. `tenants` (ISPs, if multi-tenant) — or skip if confirmed single-tenant in Phase 0.
2. `users` (staff/admin), `roles`, `permissions` (via Spatie).
3. `customers` — profile, KYC fields (NID number, NID photo, address, geo-coordinates, service address vs. billing address, connection type [PPPoE/Hotspot/Static], status [active/suspended/terminated/pending]).
4. `packages` — name, download/upload speed, FUP threshold, FUP throttled speed, price, billing cycle, prepaid/postpaid flag, tax rate.
5. `subscriptions` — customer ↔ package, start date, status, next_billing_date, proration flags.
6. `invoices` — number, customer_id, subscription_id, period, subtotal, tax, discount, total, status [draft/sent/paid/partial/overdue/void], due_date.
7. `invoice_items` — line items on an invoice.
8. `payments` — invoice_id, amount, method [bkash/nagad/sslcommerz/bank/cash], gateway_reference, status, collected_by (for field cash agents).
9. `resellers` — hierarchy (self-referencing parent_id), commission_rate, wallet_balance.
10. `network_devices` — routers, OLTs, switches: vendor, model, IP, SNMP community, location.
11. `olts` / `onus` — GPON-specific: OLT, PON port, ONU serial, optical signal level, assigned customer.
12. `radius_customers` — mapping table linking `customers.id` to the FreeRADIUS `radcheck`/`radacct` username (fully built in Phase 7).
13. `tickets` — support tickets: customer_id, category, priority, status, assigned_to, SLA due.
14. `inventory_items` — equipment stock: type (router/ONU/cable), serial, status (in-stock/assigned/faulty), assigned_to_customer.
15. `notifications_log` — audit of every SMS/email/push sent.
16. `activity_log` (from the Spatie package, automatic).

### Tasks
1. Write migrations for all entities above with correct FKs and indexes — especially on `customers.status`, `invoices.due_date`, `invoices.status`, `subscriptions.next_billing_date`, since these get queried constantly.
2. Write Eloquent models with relationships fully defined (`hasMany`, `belongsTo`, etc.) and `$casts` for money/enum fields.
3. Define PHP enums (`app/Enums`) for every status field instead of magic strings.
4. Write model factories + seeders for realistic test data (at least 500 fake customers, packages, invoices) to sanity-check query performance early.
5. Add DB-level constraints where they matter (e.g. `CHECK (total >= 0)` on invoices) — don't rely on app-layer validation alone for financial integrity.

### Definition of Done
- [ ] `php artisan migrate:fresh --seed` runs clean.
- [ ] Every model has a passing factory.
- [ ] A simple ER diagram is checked into `/docs/schema.md` for human reference.
- [ ] A query against 500+ seeded customers for "all overdue invoices" returns in reasonable time with the indexes in place (a sanity check, not a full load test — that's Phase 17).

---

## Phase 2 — Authentication & Multi-Role Access Control

**Goal:** Every human and system actor (admin, NOC engineer, support agent, reseller, field technician, customer) can authenticate appropriately and is restricted to what their role should see.

**Depends on:** Phase 1.

### Tasks
1. Configure Laravel's default auth guard for staff using Filament's built-in auth.
2. Configure Sanctum for customer-facing API/mobile app token auth, and separately for the reseller portal if it is API-driven.
3. Install and configure `spatie/laravel-permission`; seed roles: `super_admin`, `admin`, `noc_engineer`, `support_agent`, `billing_agent`, `reseller`, `field_technician`, `customer`.
4. Define permission sets per role (e.g. `billing_agent` can view/edit invoices but not network devices; `noc_engineer` is the reverse).
5. Apply Filament panel access control (`canAccessPanel`) so customers/resellers never reach `/admin`.
6. Build separate auth flows: staff login (`/admin/login`), reseller login (own portal or scoped Filament panel), customer login (API now, portal UI in Phase 14).
7. Implement password policies, login rate limiting, and 2FA for `super_admin`/`admin` roles at minimum (Filament supports this natively).
8. Audit logging: every login/logout and every permission-denied attempt written via `spatie/laravel-activitylog`.

### Definition of Done
- [ ] Each of the 8 roles can log in through its correct entry point.
- [ ] A billing agent cannot reach network-device screens; a NOC engineer cannot reach payment gateway settings — verified with feature tests, not just manual clicking.
- [ ] 2FA enforced for admin-tier roles.
- [ ] Failed-login rate limiting confirmed with a test.
- [ ] **Human check:** open `/admin` in a real browser, log in as the seeded admin user, and confirm the login screen and role-appropriate menu are visibly correct.

---

## Phase 3 — Customer / Subscriber Management (CRM)

**Goal:** Full subscriber lifecycle — lead → KYC onboarding → active connection → package change → suspension → termination — manageable from the admin panel.

**Depends on:** Phase 1, Phase 2.

### Tasks
1. Filament resource for `Customer` with full CRUD: profile, KYC document upload (NID front/back, signature), service address with a map picker (lat/lng), billing address, connection type.
2. Lead/pipeline tracking before a lead becomes a customer: `leads` table + Filament resource, statuses (new/contacted/site-survey/converted/lost), assigned sales staff.
3. Site survey / feasibility workflow: can this address be served by an existing OLT/POP? (Build the field now; wire the real logic once Phase 8's network data exists.)
4. Customer status state machine: `pending → active → suspended → terminated`, with reasons logged (non-payment, customer request, fraud, etc.). Implement this as an explicit, well-tested state machine — not ad hoc status writes scattered across the codebase.
5. Package change / upgrade / downgrade requests, with a proration hook (the math is implemented in Phase 5; the request/approval workflow is built here).
6. Customer notes/timeline — every call, complaint, and technician visit logged against the customer.
7. Bulk actions: bulk suspend, bulk SMS, bulk export (CSV) with proper pagination/chunking for 100k+ rows — never `->get()` the whole table.
8. Search/filter by name, phone, NID, area/zone, package, status — this is the single most-used screen in the system, so index accordingly (see Phase 1).
9. A minimal home dashboard in Filament with a handful of stat widgets (total customers, active/suspended/pending counts, leads-in-pipeline count). Keep it simple — this is not Phase 12's full reporting suite, it's the first real thing the project owner sees on landing at `/admin`, so make sure it's genuinely there and not buried in a menu.

### Definition of Done
- [ ] Full customer lifecycle demonstrable end-to-end in staging: lead → converted → active → suspended → reactivated → terminated.
- [ ] KYC documents upload, are stored securely (not publicly accessible URLs), and are viewable only by authorized roles.
- [ ] Customer list view performs acceptably with 100k+ seeded rows (paginated, not fully loaded).
- [ ] Every status transition is logged with actor + reason.
- [ ] **Human check:** log into `/admin`, land on a dashboard showing live customer stat widgets (not zeros/placeholders), then create, edit, and search a real customer record entirely by clicking around in the browser — no tinker/artisan commands needed to see it work.

---

## Phase 4 — Package, Plan & Pricing Engine

**Goal:** A flexible package/pricing model that supports the real messiness of ISP pricing — promos, custom per-customer pricing, FUP, bundles.

**Depends on:** Phase 1.

### Tasks
1. Filament resource for `Package`: name, speed (down/up in Mbps), price, billing cycle (monthly/quarterly/yearly), prepaid vs. postpaid, tax treatment, active/archived.
2. Fair Usage Policy (FUP): data cap threshold, throttled speed after cap, cap reset cycle — stored as package attributes, consumed later by the RADIUS layer (Phase 7).
3. Promotional pricing: time-boxed discounts on a package, or discount codes/vouchers applied at subscription time.
4. Custom per-customer override pricing (common in ISP business when a large customer negotiates a special rate) — model as an explicit override record tied to the subscription, never by mutating the shared package price.
5. Add-ons: static IP, extra device slot, OTT/IPTV bundle — a separate `add_ons` table, many-to-many with subscriptions.
6. Bundle packages (internet + IPTV + phone), only if actually in scope — confirm with the human before building; log an Open Question in `PROGRESS.md` if unclear rather than assuming.
7. Package availability by zone/area, since not every package is sellable everywhere depending on network capacity.

### Definition of Done
- [ ] Admin can create/edit/archive packages without touching code.
- [ ] A promo code demonstrably changes the price on a new subscription and expires correctly.
- [ ] A custom per-customer price override is demonstrable without mutating the base package.
- [ ] FUP fields exist and are ready for Phase 7 to consume (even though enforcement logic lives there).

---

## Phase 5 — Billing & Invoicing Engine

**Goal:** Accurate, automated recurring billing — invoice generation, proration, taxes, late fees, and auto-suspend/reactivate tied to payment status. This is the financial core of the product: test it harder than anything else in the system.

**Depends on:** Phase 1, Phase 3, Phase 4.

### Tasks
1. Scheduled billing run that generates invoices for every active subscription whose billing cycle is due, implemented as a **queued job per customer** (not one giant synchronous loop) so a single failure doesn't block the whole run. The job must be idempotent — re-running it for a customer who already has this cycle's invoice must never create a duplicate.
2. Proration logic: mid-cycle activation, package upgrade/downgrade, and mid-cycle suspension must all prorate correctly. Write this as a small, heavily unit-tested pure function — this is where billing bugs live.
3. Invoice numbering: sequential, gapless per tenant, legally defensible for tax/audit purposes. Generate under a DB transaction with locking, never in application memory where a race condition could create duplicate numbers.
4. Tax/VAT calculation per invoice line, at a configurable rate.
5. Invoice PDF generation (`spatie/laravel-pdf` or `barryvdh/laravel-dompdf`) with proper ISP letterhead, itemized lines, tax breakdown.
6. Late fee logic: configurable grace period (e.g. 5 days), late fee amount/percentage, applied automatically via a scheduled job.
7. Auto-suspend: customer crosses the grace period unpaid → subscription suspended → **an event fires that Phase 7 listens to** in order to disable RADIUS access. Keep billing and network-provisioning logic decoupled via Laravel events, not direct method calls, so each module stays independently testable.
8. Auto-reactivate: payment received for a suspended customer → event fires → Phase 7 re-enables RADIUS access.
9. Dunning: repeated payment reminders on a schedule (e.g. day 1, day 3, day 7 overdue) before suspension, via events into Phase 11's notification layer — not direct calls.
10. Prepaid flow: customer must have a positive balance/active voucher before service starts; balance decremented on a usage-cycle basis rather than invoice-then-pay.
11. Credit notes / refunds / write-offs as first-class records — never as deleted or edited invoices (immutable invoice history is a compliance requirement).
12. A reconciled ledger/statement view per customer (running balance, all invoices, payments, credits) built as one reusable query/service — this is what support agents and the customer portal both need, so don't duplicate it per screen.

### Definition of Done
- [ ] Billing run for 100k seeded subscriptions completes within an acceptable window (measure it — this is scale-sensitive).
- [ ] Re-running the same billing cycle's job twice does not create duplicate invoices (idempotency test required).
- [ ] Proration unit tests cover: mid-cycle activation, upgrade, downgrade, mid-cycle suspension.
- [ ] Invoice numbers have zero gaps and zero duplicates under concurrent generation (test with parallel job execution, not just sequentially).
- [ ] Suspend/reactivate events are demonstrably fired and consumed (log a stub listener and confirm firing even before Phase 7 is built).
- [ ] Every invoice is a static, immutable PDF snapshot — later package price changes never alter a historical invoice.

---

## Phase 6 — Payment Gateway Integration

**Goal:** Customers and field agents can pay/record payment through every channel the business actually uses, with reconciliation that doesn't require manual spreadsheet work.

**Depends on:** Phase 5.

### Tasks
1. Integrate bKash, Nagad, and SSLCommerz (covers card/bank/mobile-banking in one gateway) via their official APIs — build each as an isolated `app/Services/Payments/{Gateway}Service.php` implementing a shared `PaymentGatewayContract` interface, so adding a new gateway later doesn't touch billing logic.
2. Webhook/callback handlers for each gateway, with signature verification. Never trust a callback without verifying its signature — this is a common real-world fraud vector.
3. Manual/cash payment entry for field collection agents, with the collecting agent's ID logged against the payment for accountability.
4. Payment reconciliation: automatic matching of gateway settlement reports against recorded payments; flag mismatches for manual review rather than silently ignoring them.
5. Partial payments and split payments across multiple invoices — decide and document the allocation order (oldest invoice first is the common default) in `PROGRESS.md` if not specified by the human.
6. Idempotency keys on all payment-initiating requests to prevent double-charging on retry or duplicate webhook delivery.
7. Refund flow tied to the credit-note model from Phase 5.
8. Wallet/prepaid balance top-up flow for prepaid customers.

### Definition of Done
- [ ] Each gateway integration tested against its sandbox environment end-to-end (initiate → callback → invoice marked paid).
- [ ] A duplicate webhook delivery (simulate by sending the same callback twice) does not double-credit a payment.
- [ ] Cash payments recorded by a field agent are attributed and auditable.
- [ ] Reconciliation report correctly highlights at least one deliberately-mismatched test case.

---

## Phase 7 — FreeRADIUS AAA Integration

**Goal:** Every active, paid subscription can authenticate onto the network via PPPoE (or Hotspot), gets the correct bandwidth for their package, and is automatically cut off when suspended — all driven from Laravel, not managed by hand in RADIUS.

**Depends on:** Phase 1, Phase 5 (for the suspend/reactivate events).

### Architecture Decision
FreeRADIUS uses **PostgreSQL** (via the `rlm_sql_postgresql` module), sharing the same database server as the main app but recommended as a **separate schema or database** (`radius`) from the app schema. This keeps FreeRADIUS's own table conventions (`radcheck`, `radreply`, `radacct`, `radgroupcheck`, `radgroupreply`, `nas`, `radpostauth`) intact and upgrade-safe, while Laravel writes to it through a dedicated second DB connection (`config/database.php` → `radius` connection) instead of force-fitting FreeRADIUS's tables into the app's own migrations.

### Tasks
1. Install FreeRADIUS 3.2.x (3.2.10 at time of writing — check freeradius.org/releases for anything newer before installing), configure `rlm_sql` with the PostgreSQL driver, and load the standard FreeRADIUS PostgreSQL schema (`schema.sql` from the FreeRADIUS distribution) into the `radius` database/schema.
2. Add a second Laravel DB connection (`radius`) pointing at that schema; build a thin `RadiusUser` Eloquent model (with `$connection = 'radius'`) for `radcheck`/`radreply`, rather than scattering raw queries around.
3. Build a `RadiusProvisioningService`: given a `Customer` + active `Subscription`, it writes/updates the corresponding `radcheck` (auth) and `radreply` (bandwidth attributes — e.g. `Mikrotik-Rate-Limit` or standard RADIUS bandwidth AVPs depending on NAS vendor) entries, and removes them on termination.
4. Listen for the suspend/reactivate events from Phase 5. On suspend, either disable the `radcheck` entry (auth fails on next attempt) or issue a RADIUS CoA/Disconnect-Request against the NAS for an immediate cutoff of an already-online session. Default to implementing CoA disconnect, since ISPs generally need immediate cutoff for non-payment rather than "whenever they happen to reconnect."
5. Configure the NAS clients table (`nas`) for each MikroTik router acting as a RADIUS client, with shared secrets stored encrypted, never in plaintext config.
6. Implement FUP enforcement: a scheduled job checks `radacct` accounting data against the package's FUP cap and swaps the customer's bandwidth profile (via a `radreply` update + CoA) when they cross the threshold, and again when the cycle resets.
7. Live session visibility: query `radacct` for currently-online sessions (used by both the NOC dashboard in Phase 8 and customer support in Phase 10) — build as a reusable query service, not duplicated SQL.
8. Support both PPPoE and Hotspot (voucher-based) auth flows if the business needs both; confirm scope with the human and log an Open Question if unclear rather than assuming.

### Definition of Done
- [x] A newly-activated customer in Laravel can successfully authenticate against FreeRADIUS from a real or simulated NAS (test with the `radtest` CLI at minimum).
- [x] Bandwidth attributes returned in `radreply` match the customer's package speed.
- [x] Suspending a customer in Laravel measurably disconnects or blocks their RADIUS session within an acceptable, defined time window.
- [x] FUP threshold crossing demonstrably changes the served bandwidth profile.
- [x] `radacct` session data is queryable from Laravel without raw SQL scattered through controllers.

---

## Phase 8 — Network Device Management (MikroTik / OLT / NOC)

**Goal:** Visibility and control over the physical network — routers, OLTs, ONUs — from within Fiberloop, so NOC staff don't need to jump between five different vendor tools.

**Depends on:** Phase 1, Phase 7.

### Tasks
1. MikroTik RouterOS API integration (`app/Services/Network/MikroTikService.php`): read interface traffic, active PPPoE sessions, apply/adjust queue/rate-limit, reboot — for each router registered in `network_devices`.
2. SNMP polling service for uptime/health/interface stats across all registered devices — a scheduled job, with results stored time-series style (a `device_metrics` table, or a dedicated time-series store if real polling interval × device count justifies it — document the decision either way).
3. OLT/ONU (GPON/EPON) management: provision a new ONU against an OLT/PON port, monitor optical signal level (Rx/Tx power), and map ONU ↔ customer. Support only the OLT vendor(s) actually in use in the deployment (e.g. Huawei, ZTE, VSOL, BDCOM, Fiberhome, C-Data each use different management protocols) — confirm which vendor(s) before building, don't build speculative generic support for all of them.
4. NOC dashboard (Filament): map or list view of all devices with live status (up/down/degraded), with alerting on threshold breaches (device down, optical signal below threshold, link saturation).
5. Alerting integration: NOC alerts must be able to trigger notifications (Phase 11) to on-call engineers, not just sit in a dashboard nobody is watching.
6. Outage tracking: when a device/area goes down, log it as an incident and correlate affected customers, so support (Phase 10) can see "this ticket is a known, already-tracked network issue" instead of treating every call as new.
7. IP address pool management (static IP assignment/tracking, DHCP pool if used for Hotspot).

### Definition of Done
- [ ] At least one real or simulated MikroTik device is polled successfully and its live PPPoE session count is visible in the admin panel.
- [ ] A simulated device-down event produces a visible NOC alert.
- [ ] ONU ↔ customer mapping is queryable (even if only one OLT vendor is wired up end-to-end for v1).
- [ ] An outage correlates correctly to the list of affected customer accounts.

---

## Phase 9 — Reseller / Franchise Management

**Goal:** Sub-resellers/franchise partners can manage their own customer subset, with commission tracking and a wallet-based settlement model — a standard structure in the Bangladesh ISP market.

**Depends on:** Phase 1, Phase 2, Phase 3, Phase 5.

### Tasks
1. Reseller hierarchy: self-referencing parent/child structure (a reseller can have sub-resellers), each scoped to see only "their" customers, enforced via a global Eloquent scope — not per-query filtering scattered around the codebase.
2. Reseller portal (scoped Filament panel or separate lightweight panel): customer management, billing visibility, and complaint handling limited to their own customer base — reuse Phase 3's customer CRUD under a restricted scope rather than rebuilding it.
3. Commission model: percentage or flat-fee per customer/package, calculated automatically when the underlying invoice is paid (hook into Phase 5's payment-received event).
4. Reseller wallet: prepaid balance the reseller tops up, debited for services provisioned under them, credited for commission earned — every movement logged immutably (same principle as the customer ledger in Phase 5).
5. Reseller-initiated actions that need approval (large discounts, package changes above a threshold) route to an admin approval queue rather than executing unrestricted.
6. Reporting: per-reseller revenue, collection rate, customer count, churn — feeds Phase 12's reporting layer.

### Definition of Done
- [ ] A reseller user only ever sees their own customers, verified with feature tests — a scoped-out record must be genuinely unreachable, not just hidden from the menu.
- [ ] Commission is correctly calculated and credited on a real paid-invoice test case.
- [ ] Wallet balance never goes negative without an explicit, logged override.
- [ ] A sub-reseller hierarchy at least 2 levels deep is demonstrable.

---

## Phase 10 — Support Ticketing & Field Operations

**Goal:** Customer complaints and installation/repair jobs are tracked from creation to resolution with SLA visibility, and field technicians have what they need on a phone-sized screen.

**Depends on:** Phase 1, Phase 3, Phase 8 (for outage correlation).

### Tasks
1. Ticket model: category (billing/technical/installation/complaint), priority, status, SLA due-by (computed from priority + category), assigned agent/technician.
2. Auto-correlation with known network outages from Phase 8 — if a customer in an affected area opens a "no internet" ticket during a known outage, link it automatically instead of dispatching a redundant technician.
3. Technician dispatch: assign field jobs (installation, repair) with customer address/geo-coordinates, status (assigned/en route/on-site/completed), and required equipment (linking to Phase 15 inventory — technician "checks out" equipment for the job).
4. SLA breach alerting to supervisors.
5. Customer-visible ticket status (surfaces in Phase 14's portal — build the underlying query/API here, wire the UI there).
6. Internal notes vs. customer-visible notes as a strict distinction on tickets. This gets missed often and causes real embarrassment (internal notes leaking to customers) — enforce it at the field/serialization level, not just by convention.

### Definition of Done
- [ ] Ticket created → assigned → resolved lifecycle demonstrable with an SLA timer visible.
- [ ] A ticket opened during a simulated outage auto-links to that outage.
- [ ] A technician can update job status from a mobile-sized view.
- [ ] Internal notes are verified unreachable via the customer-facing API (test this, don't assume).

---

## Phase 11 — Notifications (SMS / Email / Push)

**Goal:** Every important event (bill due, payment received, service suspended, ticket update, outage) reaches the customer through the right channel, logged, without becoming spam.

**Depends on:** Phase 5 (billing events), Phase 10 (ticket events).

### Tasks
1. Integrate a local SMS gateway provider — confirm which provider the business actually uses (Bangladesh has several) rather than hardcoding one speculatively. Build against Laravel's Notification channel abstraction so swapping providers later is a config change, not a rewrite.
2. Email via Laravel's mail system: transactional templates for invoice, payment confirmation, suspension notice, welcome.
3. Push notifications for the mobile app (Phase 14) via FCM.
4. Every notification sent is logged in `notifications_log` (channel, recipient, template, status, timestamp) — needed for support ("did the customer actually get the SMS?") and for compliance.
5. Notification preferences per customer — some may opt out of promotional SMS, but never of critical billing/service notices.
6. Templated, versioned message content (a simple DB-backed template table with placeholders) so support/marketing can edit wording without a code deploy.
7. Rate limiting/batching for bulk sends (e.g. "bill due tomorrow" to 50,000 customers) via queued, throttled jobs — never a tight loop that could blow through provider rate limits.

### Definition of Done
- [ ] SMS, email, and push each demonstrably deliver in a sandbox/test environment.
- [ ] A bulk send to a large seeded customer batch completes via queued, throttled jobs without errors.
- [ ] `notifications_log` accurately reflects delivery status for each channel.
- [ ] Opt-out is respected for promotional messages and correctly ignored for critical service notices.

---

## Phase 12 — Filament Admin Panel: Dashboards & Reports

**Goal:** Every role has a dashboard answering "what do I need to know/do right now," and management has the reports needed to run the business without raw SQL.

**Depends on:** Phases 3, 5, 6, 8, 9, 10 (pulls data from all of them).

### Tasks
1. Role-specific dashboards: admin (revenue, collection rate, active/suspended/churned counts, outstanding dues), NOC (device health summary, active outages), support (open tickets by SLA status), reseller (their own revenue/customers, reusing Phase 9's scoping).
2. Core reports: revenue by period, collection rate, outstanding dues (aging report: 0–30 / 31–60 / 61–90 / 90+ days), churn rate, area/zone-wise performance, package popularity.
3. Export to CSV/PDF/XLSX for every report — finance and management will need to hand these to accountants or regulators.
4. Filament widgets built as reusable, performant queries. At 100k+ customer scale, dashboard widgets must use aggregate queries (counts, sums), never `Model::all()` — cache expensive aggregates in Redis with a sensible TTL rather than recomputing on every page load.
5. Global search across customers/invoices/tickets from the admin panel.
6. Saved/scheduled reports emailed periodically to management (e.g. a daily collection summary).

### Definition of Done
- [ ] Each role's dashboard loads in acceptable time against the 100k+ seeded dataset (measure it).
- [ ] Aging report totals reconcile against a manually-computed spot check.
- [ ] At least one report successfully exports in each of CSV/PDF/XLSX.
- [ ] A scheduled report email demonstrably sends on schedule.

---

## Phase 13 — AI & Analytics Layer

**Goal:** The "AI-powered" part of the product — practical, defensible AI features, not AI for its own sake.

**Depends on:** Phases 3, 5, 8 (needs real historical data to be useful — don't build this against an empty dataset).

### Architecture Decision
Split by task type:
- **In-app AI features** (support chatbot, ticket categorization/summarization, natural-language admin search) → **Laravel AI SDK**, called directly from the Laravel app. No separate service needed for these.
- **Classic ML on tabular/time-series data** (churn prediction, demand/revenue forecasting, payment-anomaly/fraud detection) → a **separate Python microservice** (FastAPI + scikit-learn/XGBoost/pandas), exposed over an internal REST API and called from Laravel. Python's ML tooling is meaningfully better suited to this than routing everything through an LLM call — use the right tool per task.

### Tasks
1. Churn prediction model: train on historical subscription/payment/ticket data (tenure, payment delays, ticket count/sentiment, usage trends) to score churn risk per customer; surface the score on the customer's admin profile and feed a "high-risk" filtered view for retention outreach.
2. Payment/usage anomaly detection: flag accounts with unusual usage spikes (possible account sharing/fraud) or unusual payment patterns.
3. Demand/revenue forecasting: project subscriber growth and revenue for management reporting (feeds Phase 12).
4. Support chatbot (Laravel AI SDK): answers common billing/account questions and escalates to a human ticket (Phase 10) when it can't help. Scope it honestly — don't let it attempt account-changing actions without explicit confirmation.
5. Retrain/monitor: define how often the churn/forecast models retrain (e.g. weekly) and track model accuracy over time so silent drift doesn't go unnoticed — log predictions vs. actual outcomes.
6. Every AI-derived score/output shown to staff is labeled as a prediction/suggestion, never presented as ground truth — this matters for trust and for avoiding bad business decisions off a wrong prediction.

### Definition of Done
- [ ] Churn score is computed and visible for the seeded customer base, with a documented (even if simple) accuracy check against historical actual churn.
- [ ] Anomaly detection flags at least one deliberately-planted anomalous test case.
- [ ] Chatbot correctly escalates an out-of-scope question to a human ticket rather than hallucinating an answer.
- [ ] The retraining job is scheduled, and its last-run/accuracy is visible in the admin panel, not just in logs.

---

## Phase 14 — Customer Self-Service Portal & Mobile App

**Goal:** Customers can see and manage their account without calling support — this reduces support load more than almost anything else in the system.

**Depends on:** Phases 2 (Sanctum auth), 3, 5, 6, 10, 11.

### Tasks
1. REST API (Sanctum-protected) exposing: account/profile, current package, invoices/payment history, pay-now (wired to Phase 6 gateways), usage stats (from Phase 7's `radacct`), ticket creation/status, package upgrade/downgrade request.
2. Flutter app (Android + iOS) consuming that API: login, dashboard (balance/due date/usage), bill pay, ticket raise/track, notification inbox, speed test.
3. Near-real-time usage display: live or near-live data usage/session status (from Phase 7).
4. Push notification registration (FCM token) tied into Phase 11.
5. Live chat widget (optional but common in this market) — only build if in scope; confirm with the human rather than assuming.
6. A web version of the same portal, only if needed — confirm with the human; log an Open Question if unclear rather than building both speculatively.
7. API rate limiting and abuse protection. This is a public-facing API surface — treat it as such from day one, not retrofitted in Phase 16.

### Definition of Done
- [ ] Full customer journey demonstrable in the app: login → view bill → pay → see payment reflected → raise ticket → see ticket status update.
- [ ] Usage data shown in-app matches `radacct` data within an acceptable freshness window.
- [ ] Push notification demonstrably received on a test device/emulator.
- [ ] The API rejects unauthenticated and cross-customer-scoped requests — customer A cannot fetch customer B's invoice by guessing an ID. Test this explicitly; it's a very common real-world vulnerability in exactly this kind of system.

---

## Phase 15 — Inventory & Asset Management

**Goal:** Track physical equipment (routers, ONUs, cable) from procurement through customer assignment through return/retirement.

**Depends on:** Phase 1, Phase 3, Phase 10 (technician checkout).

### Tasks
1. Inventory item model: type, serial/MAC, vendor, purchase date/cost, status (in-stock/assigned/faulty/retired), current holder (warehouse/technician/customer).
2. Stock in/out transactions, fully logged (who moved what, when, why).
3. Assign equipment to a customer at installation (from Phase 10's field job flow) — links `inventory_items.assigned_to_customer`.
4. Low-stock alerts per item type.
5. Faulty/return handling: equipment returned from a terminated customer goes back to stock as "needs inspection," not silently back to "available."
6. Basic procurement/purchase-order tracking, only if in scope — confirm with the human; log an Open Question rather than over-building this.

### Definition of Done
- [ ] An item's full lifecycle (received → in stock → assigned to technician → assigned to customer → returned → inspected → back in stock or retired) is demonstrable and logged.
- [ ] A low-stock alert fires correctly against a test threshold.
- [ ] Equipment assigned to a terminated customer is flagged for return, not left dangling.

---

## Phase 16 — Security & Data Hardening

**Goal:** The system is hardened against the security risks that matter most for a system holding customer PII, KYC documents, and payment data.

> **Scope note:** an earlier draft of this phase included BTRC (Bangladesh Telecommunication Regulatory Commission) subscriber reporting. That has been deliberately removed at the project owner's request — it is not being built as part of this roadmap. If it's ever needed later, it can be re-added as its own phase without disturbing anything below.

**Depends on:** Phases 3, 5, 7 (touches data from all of them).

### Tasks
1. Encrypt at rest: KYC documents, NID numbers, RADIUS shared secrets, payment gateway credentials.
2. NID/KYC data handling: restrict document access to authorized roles only, and retain per whatever internal data-retention period the business sets.
3. All admin/reseller/technician access over HTTPS only, with HSTS enabled.
4. API rate limiting on all public endpoints (customer API, webhooks).
5. SQL injection / mass-assignment audit: confirm every model has correct `$fillable`/`$guarded`, and every raw query is parameterized.
6. Dependency vulnerability scanning (`composer audit`, Dependabot or equivalent) wired into CI.
7. Secrets management: no credentials committed to git, and a real secrets manager (not just `.env` files) for production — confirm the deployment target in Phase 18 before finalizing this.
8. Backup encryption and a tested restore procedure — a backup that has never been test-restored is not a real backup.
9. A penetration-test-style pass specifically on the customer-facing API (Phase 14) — this is the system's largest external attack surface.
10. Build customer data export/delete-on-request capability — good practice and increasingly expected even without a specific legal mandate driving it.

### Definition of Done
- [ ] KYC documents are confirmed inaccessible via direct URL guessing.
- [ ] `composer audit` runs clean (or has documented exceptions).
- [ ] A real backup has been restored to a clean environment and verified to work.
- [ ] Rate limiting is confirmed on the customer API with an automated test.

---

## Phase 17 — Testing & QA

**Goal:** Confidence that the system behaves correctly under real load and real edge cases before real customers and real money touch it.

**Depends on:** All prior phases — this phase validates everything built so far.

### Tasks
1. Unit test coverage for all pure business logic: proration, commission calculation, tax calculation, FUP threshold logic — exactly the places where off-by-one and rounding bugs cause real financial damage.
2. Feature tests for every critical user journey per phase (many are already specified in each phase's Definition of Done — consolidate them and make sure they actually run in CI, not just "were true once manually").
3. Load testing the billing run and the RADIUS auth path specifically at target scale (100k+ subscribers) — these are the two operations most likely to fall over under real scale; don't assume Phase 5/7's dev-scale tests generalize without an actual load test.
4. Security testing: the checks specified in Phase 16, actually executed and results recorded — not just planned.
5. Data integrity checks: reconciliation scripts that catch drift (e.g. the sum of invoice payments should always reconcile against ledger balance) — run these as a scheduled health-check job in production, not just a one-time test.
6. UAT (user acceptance testing) with real staff on a staging environment loaded with realistic (anonymized, if using real data) data before launch.

### Definition of Done
- [ ] CI runs the full test suite on every push, and it's green.
- [ ] Load test results for the billing run and RADIUS auth are documented against the 100k+ target, with any bottlenecks either fixed or explicitly accepted as a known limitation.
- [ ] The reconciliation health-check job exists and has been verified to actually catch a deliberately-introduced discrepancy in a test.
- [ ] UAT sign-off obtained from actual business stakeholders, not just the engineering team.

---

## Phase 18 — DevOps, CI/CD & Deployment

**Goal:** Reliable, repeatable deployment with monitoring that tells you something's wrong before customers do.

**Depends on:** Phase 17 — don't automate deploying an untested system.

### Tasks
1. Dockerize the full stack: app (Octane), Horizon workers, Reverb, Nginx, FreeRADIUS, PostgreSQL, Redis.
2. Orchestration: Kubernetes if scale/ops maturity justifies it, or a simpler managed setup (e.g. Laravel Forge/Cloud) if not — decide based on actual team size/ops capacity, and log the decision and reasoning in `PROGRESS.md`.
3. CI pipeline (GitHub Actions): lint (Pint) → test (Pest) → build → deploy, with a required-green-CI branch protection rule.
4. Zero/low-downtime deploy strategy (queue worker draining, migration safety) — every migration must be safe to run against a live database with traffic.
5. Monitoring: Laravel Pulse for app performance, Sentry for error tracking, infrastructure monitoring (Prometheus/Grafana or a managed equivalent) for servers/DB/Redis/RADIUS, and uptime monitoring on customer-facing endpoints.
6. Alerting: on-call routing for critical alerts (RADIUS down, DB down, billing job failed) — not optional for a system where RADIUS downtime means every customer's internet stops working.
7. Log aggregation and a retention policy.
8. A staging environment that mirrors production configuration (not just "a smaller version") so pre-launch testing is representative.
9. Database backup automation with off-site storage, with the restore test from Phase 16 repeated here as a deployment-pipeline-verified step, not a one-off.

### Definition of Done
- [ ] A full deploy from a clean environment (staging or prod) succeeds via the CI/CD pipeline, not manual steps.
- [ ] Simulated RADIUS-down and DB-down scenarios both trigger alerts within an acceptable time window.
- [ ] A rollback has actually been performed once in staging, not just theorized.
- [ ] Backup + restore automation verified end-to-end in the deployed environment.

---

## Phase 19 — Production Launch Checklist

**Goal:** The final gate before real customers depend on this system.

**Depends on:** All prior phases Done.

### Checklist
- [ ] All prior phases' Definitions of Done are checked and verified, not assumed.
- [ ] Load test results at target scale are acceptable (Phase 17).
- [ ] Backup/restore verified in the actual production environment, not just staging.
- [ ] On-call rotation and alerting is live and has been tested with a real, announced drill.
- [ ] Data migration plan from any existing/legacy system (if applicable) is written, tested against a copy of real legacy data, and reviewed — this is a very common place for launches to go wrong; don't treat it as an afterthought.
- [ ] A rollback plan exists in case the launch needs to be reversed.
- [ ] Support staff are trained on the new system before go-live, not during.
- [ ] A soft-launch/phased rollout plan (e.g. one zone or reseller first) rather than a full cutover, if at all feasible — this significantly de-risks the launch.
- [ ] Legal/ToS/privacy policy for the customer-facing app reviewed (outside engineering scope, but launch is blocked until confirmed).
- [ ] A post-launch monitoring plan for the first 72 hours is explicitly assigned to named people, not "the team will watch it."

---

## Appendix A — Master Feature Checklist (Production-Ready Definition)

Use this as a final cross-cutting sanity check — it restates the phases above as a flat list, useful for one last sweep before Phase 19's launch gate.

**Customer/CRM:** lead pipeline · KYC onboarding · service+billing address · status lifecycle · notes/timeline · bulk actions · search/filter at scale

**Packages/Pricing:** package CRUD · FUP · promos/vouchers · per-customer overrides · add-ons · zone availability

**Billing:** automated recurring invoicing · proration · sequential invoice numbering · tax/VAT · PDF invoices · late fees · dunning · auto-suspend/reactivate · prepaid support · credit notes/refunds · customer ledger

**Payments:** bKash/Nagad/SSLCommerz · manual/cash collection · reconciliation · partial/split payments · idempotency · refunds · wallet top-up

**Network/RADIUS:** PPPoE + Hotspot auth · bandwidth-by-package · FUP enforcement · CoA disconnect on suspend · live session visibility

**Network devices:** MikroTik API control · SNMP monitoring · OLT/ONU management · NOC dashboard · outage tracking · IP pool management

**Reseller:** hierarchy · scoped access · commission · wallet · approval workflows · reporting

**Support:** ticketing · SLA · outage correlation · technician dispatch · internal vs. customer-visible notes

**Notifications:** SMS/email/push · delivery logging · preferences · templated content · throttled bulk send

**Admin/Reporting:** role dashboards · revenue/collection/aging/churn reports · CSV/PDF/XLSX export · scheduled report emails

**AI:** churn prediction · anomaly detection · demand forecasting · support chatbot · model monitoring

**Customer app:** account/billing/usage visibility · bill pay · ticket raise/track · push notifications

**Inventory:** full item lifecycle · low-stock alerts · technician checkout · return/inspection flow

**Compliance/Security:** encrypted KYC/PII · RBAC · audit logging · rate limiting · dependency scanning · tested backup+restore

**Ops:** CI/CD · monitoring+alerting · zero-downtime deploy · staging parity · on-call rotation

If any line above isn't checked off and verified, the system is not production-ready regardless of what Phase 19's checklist says — treat this appendix as the tie-breaker.

---

## Appendix B — Package/Library Reference

| Purpose | Package |
|---|---|
| Framework | `laravel/laravel` (v13) |
| Admin panel | `filament/filament` (v5) |
| RBAC | `spatie/laravel-permission` |
| Audit log | `spatie/laravel-activitylog` (v5 requires PHP 8.4 — see PHP version note) |
| Backups | `spatie/laravel-backup` |
| PDF generation | `spatie/laravel-pdf` or `barryvdh/laravel-dompdf` |
| API auth | `laravel/sanctum` |
| Queue monitoring | `laravel/horizon` |
| Realtime | `laravel/reverb` |
| Performance server | `laravel/octane` (FrankenPHP; Swoole is a fallback) |
| AI (in-app) | Laravel AI SDK |
| Multi-tenancy (if used) | `stancl/tenancy` |
| Testing | `pestphp/pest` |
| Formatting | `laravel/pint` |
| SNMP | `php-snmp` extension |

---

## Appendix C — Key Environment Variables

Build `.env.example` up incrementally as each phase is implemented — this list is the target, not a day-one requirement.

```
DB_CONNECTION=pgsql
DB_HOST= | DB_PORT=5432 | DB_DATABASE=fiberloop | DB_USERNAME= | DB_PASSWORD=

RADIUS_DB_HOST= | RADIUS_DB_PORT= | RADIUS_DB_DATABASE=radius | RADIUS_DB_USERNAME= | RADIUS_DB_PASSWORD=

REDIS_HOST= | REDIS_PORT=6379

BKASH_APP_KEY= | BKASH_APP_SECRET= | BKASH_USERNAME= | BKASH_PASSWORD=
NAGAD_MERCHANT_ID= | NAGAD_PUBLIC_KEY= | NAGAD_PRIVATE_KEY=
SSLCOMMERZ_STORE_ID= | SSLCOMMERZ_STORE_PASSWORD=

SMS_GATEWAY_API_KEY= | SMS_GATEWAY_SENDER_ID=

MIKROTIK_API_DEFAULT_PORT=8728

FCM_SERVER_KEY=

AI_SERVICE_URL=   # Python/FastAPI microservice for churn & forecasting models
```

---

## Optional / Future Extensions (not required for v1 production launch)

- HR-lite: staff attendance and salary tracking for field technicians.
- Voucher/prepaid card physical printing and batch generation for Hotspot resale.
- Franchise-to-ISP settlement billing, separate from reseller-to-customer billing.
- Network capacity planning map (visualizing OLT port saturation for expansion decisions).
- WhatsApp Business API as an additional notification channel alongside SMS/email/push.

*End of roadmap. If a phase's real-world scope turns out bigger than expected once work starts, split it into sub-phases in `PROGRESS.md` rather than silently cutting corners to stay "on schedule."*
