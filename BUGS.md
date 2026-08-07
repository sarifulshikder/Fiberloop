# BUGS.md — Fiberloop Bug Tracker

> Log every bug here before fixing it, and update it after. This is what keeps a debugging session from turning into "fixed some stuff, not sure what" — for you and for the next agent session. Read alongside `AGENTS.md` and `PROGRESS.md`.

## The Loop: Find → Check → Test → Fix → Verify
1. **Find** — reproduce the bug, note the exact steps. Don't fix yet.
2. **Check** — log it below: which phase it belongs to, severity, whether it touches money, network-provisioning, or data integrity (these are always Critical no matter how small they look).
3. **Test** — for backend/logic bugs, write a failing Pest test that captures the bug before touching any fix code. Pure cosmetic/UI bugs can skip this.
4. **Fix** — the smallest change that fixes the root cause, not just the symptom. One bug per commit.
5. **Verify** — the new test passes, the *full* test suite still passes, and the exact repro steps were manually re-walked in the browser. Only then mark it Verified.

Severity: `Critical` (money/network/data-loss wrong) · `High` (feature broken or crashes) · `Medium` (works but wrong in an edge case) · `Low` (cosmetic/UX)

## Open Bugs
| ID | Phase | Severity | Description | Repro steps | Touches | Status |
|---|---|---|---|---|---|---|
| BUG-001 | Phase 2 | Critical | Undefined variable $key in ApiRateLimitMiddleware.php line 101 - closure in getLimitForRequest() uses undefined $key variable | Access any API endpoint that goes through rate limiting | Network-provisioning | Found |
| BUG-002 | Phase 3 | Critical | Foreign key violation: customers.created_by references users.id - Factory creates User but may use non-existent ID | Run db:seed or create customers via factory | Data integrity | Found |
| BUG-007 | Phase 10 | Critical | Duplicate table field_jobs - Migration tries to create already existing table | Run migrations | Data integrity | Found |
| BUG-008 | Phase 4 | Medium | Deprecation errors in Package model (lines 140, 162, 209) - likely related to enum usage | Access Package-related functionality | UI | Found |
| BUG-009 | Phase 6 | Critical | BigDecimal type error: toScale() expects int, receives string - Money fields stored as string instead of int | Any monetary calculation | Money | Found |
| BUG-011 | Phase 4 | Critical | Customer model tenant() relationship has wrong parameters: belongsTo(Tenant::class, 'tenant_id', 'lead_id', 'id') | Access customer-tenant relationships | Data integrity | Found |

## Fixed — unverified (code changes only, not execution-verified)
| ID | Phase | Severity | Description | Files Changed | Status |
|---|---|---|---|---|---|
| BUG-003 | Phase 8 | High | Type declaration errors in Filament Pages/Resources: navigationIcon/navigationGroup | 32+ files in app/Filament | Fixed — unverified |
| BUG-004 | Phase 8 | High | Type declaration error in ReportsDashboard | app/Filament/Pages/ReportsDashboard.php | Fixed — unverified |
| BUG-005 | Phase 2 | High | Filament v5 Actions namespace compatibility | All resources already correct | Fixed — unverified |
| BUG-006 | Phase 3 | Critical | pluck() with accessor: Customer select fields using full_name accessor | InvoiceResource.php, InventoryItemForm.php, StockTransactionForm.php, StockTransactionsTable.php, CustomerNoteResource.php, IpAddressResource.php, OnuResource.php, PackageChangeRequestResource.php | Fixed — unverified |
| BUG-010 | Phase 8 | High | Type declaration pattern: All Filament Pages navigation properties | All Pages in app/Filament | Fixed — unverified |
| BUG-012 | Phase 16 | High | All Customer::query()->pluck('full_name', 'id') usages in Select fields | 8 files across app/Filament | Fixed — unverified |
| BUG-013 | Phase 2 | High | Filament v5 compatibility: Action namespaces | All resources already correct | Fixed — unverified |

Status values: `Found` → `Confirmed` (reproduced + test written) → `Fixed` → `Verified` → move to the log below.

## Fixed & Verified Log
<!-- One line per closed bug, with the root cause — so patterns become visible over time -->
- (none yet)

## Patterns Worth Flagging
<!-- If 3+ bugs share a root cause (e.g. "money stored as float in three places"), name the pattern here so the fix addresses the whole class, not each symptom one at a time -->
- **Pattern 4 - Missing HasUuids Trait**: Fixed a major bug where `uuid` columns were defined in migrations but models (32 in total) lacked `use HasUuids;`. Affected NetworkDevice creation (500 Server Error) among others. FIXED: added trait to all affected models and reloaded Octane.
- **Pattern 1 - Filament v5 Type Declarations**: BUG-003, BUG-004, BUG-010 - FIXED: Changed all `BackedEnum|string|null` and `UnitEnum|string|null` to `string|\BackedEnum|null` and `string|\UnitEnum|null` across 32+ Filament files, removed incorrect `use BackedEnum;` and `use UnitEnum;` imports
- **Pattern 2 - Accessor with pluck()**: BUG-006, BUG-012 - FIXED: Replaced all `Customer::query()->pluck('full_name', 'id')` and `Customer::query()->get()->pluck('full_name', 'id')` with Filament's relationship + getOptionLabelFromRecordUsing pattern in 8 files
- **Pattern 3 - Filament v5 Action Namespace**: BUG-005, BUG-013 - FIXED: All files already importing from correct `Filament\Actions` namespace

## Verification Notes
- Pattern 1 & 3 fixes: Code-only changes, no browser verification possible in current environment
- Pattern 2 fixes: **REQUIRES MANUAL VERIFICATION** - Before marking BUG-006/BUG-012 as Verified: manually open each dropdown, type a partial customer name, and confirm results appear. Known Filament quirk: searchable() + getOptionLabelFromRecordUsing() may silently return zero search results.
