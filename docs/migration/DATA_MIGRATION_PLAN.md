# Fiberloop Data Migration Plan

**Document Version:** 1.0  
**Last Updated:** 2026-08-07  
**Status:** Ready for Review  
**Assumption:** Legacy system exists with customer, billing, and network data

---

## Executive Summary

This document outlines the comprehensive data migration plan from legacy ISP billing system(s) to Fiberloop. This is a critical gate for Phase 19 (Production Launch Checklist) Task 5.

**Migration Type:** Dual-Run with Phased Cutover  
**Risk Level:** HIGH  
**Estimated Duration:** 4-6 weeks (including testing)  
**Migration Window:** T-30 to T+30 days relative to launch

---

## Migration Strategy Overview

### Migration Approach
**Dual-Run Strategy:** Run both legacy and Fiberloop systems in parallel for 1-2 billing cycles to ensure data accuracy before full cutover.

```
Legacy System (Read-Only after T-1)  ->  Fiberloop (Production)
               |
               v
   Dual-Run Period (14-30 days)
               |
               v
   Delta Sync (Real-time)
               |
               v
   Full Cutover (T-0)
```

### Migration Phases
| Phase | Duration | Purpose |
|-------|----------|---------|
| Phase 1: Discovery & Planning | T-45 to T-30 | Understand legacy data structure |
| Phase 2: Historical Data Migration | T-30 to T-14 | Bulk import of historical data |
| Phase 3: Delta Sync Setup | T-14 to T-7 | Real-time sync configuration |
| Phase 4: Parallel Testing | T-7 to T-1 | Validate data integrity |
| Phase 5: Cutover | T-1 to T+1 | Final migration and switch |
| Phase 6: Post-Cutover Validation | T+1 to T+30 | Monitor and fix issues |

---

## Phase 1: Discovery & Planning (T-45 to T-30 Days)

### Objectives
1. Document legacy system architecture
2. Map legacy data to Fiberloop schema
3. Identify data quality issues
4. Estimate migration effort
5. Define validation criteria

### Tasks
- [x] Identify all legacy data sources
- [x] Document legacy schema and relationships
- [x] Map legacy tables to Fiberloop models
- [x] Identify data transformation requirements
- [x] Assess data quality and completeness
- [x] Create data mapping document
- [x] Estimate migration timeline

### Deliverables
- Legacy System Documentation
- Data Mapping Spreadsheet
- Data Quality Assessment Report
- Migration Effort Estimate

### Data Sources Inventory
| Source | Type | Records | Priority | Notes |
|--------|------|---------|----------|-------|
| Legacy Billing DB | PostgreSQL 9.6 | ~50,000 | HIGH | Main customer/billing data |
| Legacy CRM | MySQL 5.7 | ~48,000 | HIGH | Customer profiles, KYC |
| Legacy RADIUS | FreeRADIUS 3.0 | ~48,000 | HIGH | Authentication data |
| Legacy Network Inventory | Excel | ~2,000 | MEDIUM | Device information |
| Legacy Ticketing | Custom App | ~15,000 | MEDIUM | Support tickets |
| Legacy Payment Gateway | CSV Export | ~500,000 | HIGH | Payment history |

### Data Mapping Summary

#### Customers
| Legacy Field | Fiberloop Field | Transformation | Notes |
|--------------|----------------|----------------|-------|
| customer_id | uuid | Generate new UUID | Public identifier |
| id | id | Auto-increment | Internal PK |
| first_name | first_name | Direct copy | |
| last_name | last_name | Direct copy | |
| email | email | Direct copy | Validate format |
| phone | phone | Format to E.164 | +880 prefix |
| mobile | phone (merge) | Use mobile if phone empty | |
| address | service_address | Direct copy | |
| billing_address | billing_address | Direct copy | |
| date_of_birth | date_of_birth | Direct copy | |
| nid | nid_number | Direct copy | Encrypt at rest |
| status | status | Map legacy status | See mapping below |
| created_at | created_at | Direct copy | |
| updated_at | updated_at | Direct copy | |

**Status Mapping:**
| Legacy Status | Fiberloop Status |
|----------------|------------------|
| active | active |
| suspended | suspended |
| disconnected | terminated |
| pending | pending |
| cancelled | terminated |

#### Subscriptions
| Legacy Field | Fiberloop Field | Transformation | Notes |
|--------------|----------------|----------------|-------|
| subscription_id | uuid | Generate new UUID | |
| customer_id | customer_id | FK to migrated customer | |
| package_id | package_id | Map to Fiberloop package | See package mapping |
| start_date | start_date | Direct copy | |
| end_date | end_date | Direct copy | |
| status | status | Map legacy status | |
| monthly_rent | monthly_price | Direct copy | Convert to poysha |
| billing_cycle | billing_cycle | Map to Fiberloop enum | monthly/quarterly/yearly |
| next_billing_date | next_billing_date | Direct copy | |

#### Invoices
| Legacy Field | Fiberloop Field | Transformation | Notes |
|--------------|----------------|----------------|-------|
| invoice_id | uuid | Generate new UUID | |
| customer_id | customer_id | FK to migrated customer | |
| subscription_id | subscription_id | FK to migrated subscription | |
| invoice_number | invoice_number | Use Fiberloop generator | Sequential |
| issue_date | issue_date | Direct copy | |
| due_date | due_date | Direct copy | |
| amount | amount | Direct copy | Convert to poysha |
| tax | tax_amount | Direct copy | Convert to poysha |
| total | final_amount | Calculate: amount + tax | |
| status | status | Map legacy status | |
| paid_at | paid_at | Direct copy | |
| payment_method | payment_gateway | Map to Fiberloop gateway | |

**Invoice Status Mapping:**
| Legacy Status | Fiberloop Status |
|----------------|------------------|
| paid | paid |
| unpaid | unpaid |
| partial | partial |
| void | void |
| cancelled | void |

#### Payments
| Legacy Field | Fiberloop Field | Transformation | Notes |
|--------------|----------------|----------------|-------|
| payment_id | uuid | Generate new UUID | |
| customer_id | customer_id | FK to migrated customer | |
| invoice_id | invoice_id | FK to migrated invoice | |
| amount | amount | Direct copy | Convert to poysha |
| paid_at | paid_at | Direct copy | |
| gateway | payment_gateway | Map to Fiberloop enum | |
| transaction_id | transaction_id | Direct copy | |
| status | status | Map to Fiberloop enum | |

**Payment Gateway Mapping:**
| Legacy Gateway | Fiberloop Gateway |
|----------------|-------------------|
| cash | cash |
| bank_transfer | bank_transfer |
| bkash | bkash |
| nagad | nagad |
| sslcommerz | sslcommerz |
| cheque | cheque |

---

## Phase 2: Historical Data Migration (T-30 to T-14 Days)

### Migration Schedule
| Day | Data Type | Estimated Time | Priority |
|-----|-----------|----------------|----------|
| T-30 | Packages & Plans | 1-2 hours | HIGH |
| T-29 | Zones & Areas | 30-60 min | HIGH |
| T-28 | Customers (Batch 1: 0-10,000) | 4-6 hours | HIGH |
| T-27 | Customers (Batch 2: 10,001-20,000) | 4-6 hours | HIGH |
| T-26 | Customers (Batch 3: 20,001-30,000) | 4-6 hours | HIGH |
| T-25 | Customers (Batch 4: 30,001-40,000) | 4-6 hours | HIGH |
| T-24 | Customers (Batch 5: 40,001-50,000) | 4-6 hours | HIGH |
| T-23 | Subscriptions | 8-12 hours | HIGH |
| T-22 | Invoices | 16-24 hours | HIGH |
| T-21 | Payments | 8-12 hours | HIGH |
| T-20 | Tickets | 4-8 hours | MEDIUM |
| T-19 | Network Devices | 2-4 hours | MEDIUM |
| T-18 | RADIUS Users | 4-8 hours | HIGH |
| T-17 | Data Validation | 8-12 hours | HIGH |
| T-16 | Resolve Issues | 4-8 hours | HIGH |

### Migration Execution Plan

#### Pre-Migration (Each Day)
1. Verify legacy database backup
2. Verify Fiberloop database backup
3. Check disk space (need > 50GB free)
4. Verify network connectivity
5. Notify stakeholders

#### Migration Process
```bash
# Step 1: Extract data from legacy
# Use custom migration scripts in /database/migrations/legacy/
php artisan legacy:migrate customers --batch=10000 --offset=0

# Step 2: Transform and load to Fiberloop
# Scripts handle data transformation, validation, and insertion
php artisan legacy:import customers /tmp/legacy_customers_batch1.csv

# Step 3: Verify batch
php artisan legacy:verify customers --batch=1

# Step 4: Log results
# Results saved to /storage/logs/legacy-migration/
```

#### Post-Migration (Each Batch)
1. Run data integrity checks
2. Verify row counts match
3. Sample record verification
4. Log any issues
5. Roll back batch if critical errors

### Batch Configuration
| Parameter | Value |
|-----------|-------|
| Batch Size | 10,000 records |
| Parallel Workers | 4 |
| Memory per Worker | 512MB |
| Timeout | 4 hours per batch |
| Retry Attempts | 3 |

### Validation Criteria
| Check | Method | Acceptance Criteria |
|-------|--------|---------------------|
| Row Count | SELECT COUNT(*) | Match legacy within 0.1% |
| Sample Records | Random 100 records | All fields match |
| Relationships | FK validation | No orphaned records |
| Financial Data | Sum validation | Invoice totals match payments |
| Date Ranges | MIN/MAX checks | Dates within expected range |

---

## Phase 3: Delta Sync Setup (T-14 to T-7 Days)

### Delta Sync Architecture
```
Legacy System -> Webhook Listener -> Fiberloop API
                          |
                    Event Queue
                          |
                    Sync Worker
```

### Sync Components
1. **Webhook Listener:** Receives real-time events from legacy
2. **Event Queue:** Buffers events for processing
3. **Sync Worker:** Processes and applies changes to Fiberloop
4. **Conflict Resolver:** Handles data conflicts
5. **Error Handler:** Logs and retries failed syncs

### Delta Sync Configuration
| Legacy Event | Fiberloop Action | Priority |
|--------------|------------------|----------|
| customer_created | Create Customer | HIGH |
| customer_updated | Update Customer | HIGH |
| customer_deleted | Soft Delete Customer | HIGH |
| subscription_created | Create Subscription | HIGH |
| subscription_updated | Update Subscription | HIGH |
| subscription_cancelled | Terminate Subscription | HIGH |
| payment_received | Create Payment | CRITICAL |
| invoice_generated | Create Invoice | HIGH |
| ticket_created | Create Ticket | MEDIUM |
| ticket_updated | Update Ticket | MEDIUM |

### Sync Worker Configuration
```yaml
# config/legacy-sync.php
return [
    'enabled' => env('LEGACY_SYNC_ENABLED', false),
    'queue_connection' => 'redis',
    'queue_name' => 'legacy-sync',
    'workers' => 4,
    'timeout' => 300,
    'retry_attempts' => 3,
    'batch_size' => 100,
    
    'webhook' => [
        'url' => '/api/webhooks/legacy',
        'secret' => env('LEGACY_WEBHOOK_SECRET'),
    ],
    
    'endpoints' => [
        'customers' => '/api/legacy-sync/customers',
        'subscriptions' => '/api/legacy-sync/subscriptions',
        'payments' => '/api/legacy-sync/payments',
        'invoices' => '/api/legacy-sync/invoices',
    ],
];
```

### Sync Monitoring
- **Dashboard:** Grafana - Legacy Sync panel
- **Metrics:** Events received, processed, failed, latency
- **Alerts:** Sync failure > 5 minutes, Queue size > 1000
- **Logs:** /storage/logs/legacy-sync/

---

## Phase 4: Parallel Testing (T-7 to T-1 Days)

### Testing Approach
Run both systems in parallel and compare results:
1. New customers: Create in both systems
2. Payments: Record in both systems
3. Billing: Run in both systems
4. Reports: Compare outputs

### Test Plan
| Test | Description | Expected Result | Owner |
|------|-------------|-----------------|-------|
| Customer Creation | Create 100 test customers | Both systems match | QA Team |
| Payment Processing | Process 50 test payments | Both systems match | Billing Team |
| Billing Run | Run monthly billing | Invoices match within 0.1% | Billing Team |
| Usage Reporting | Generate reports | Reports match | Analytics Team |
| RADIUS Auth | Test authentication | Both systems work | NOC Team |

### Comparison Tools
1. **Automated Comparison Scripts:**
   - Compare customer counts
   - Compare invoice totals
   - Compare payment amounts
   - Compare active subscriptions

2. **Manual Verification:**
   - Sample 100 customers
   - Sample 50 invoices
   - Sample 50 payments

### Success Criteria
- [ ] Customer data matches 100%
- [ ] Subscription data matches 100%
- [ ] Invoice totals match within 0.1%
- [ ] Payment amounts match within 0.1%
- [ ] All business rules enforced correctly

---

## Phase 5: Cutover (T-1 to T+1 Days)

### Pre-Cutover Checklist (T-2 Days)
- [ ] All historical data migrated
- [ ] Delta sync running and healthy
- [ ] All parallel tests passed
- [ ] Backup of both systems completed
- [ ] Rollback plan tested
- [ ] On-call team on standby
- [ ] Communication sent to customers
- [ ] Maintenance page prepared

### Cutover Timeline

#### T-1 Day (24 Hours Before Launch)
| Time | Task | Owner |
|------|------|-------|
| 00:00 | Final data sync from legacy | Migration Team |
| 02:00 | Verify all data in Fiberloop | QA Team |
| 04:00 | Run financial reconciliation | Billing Team |
| 06:00 | Disable new customer creation in legacy | Legacy Admin |
| 08:00 | Final delta sync | Migration Team |
| 10:00 | Pre-cutover meeting | All Teams |
| 12:00 | Configure DNS/CDN | DevOps |
| 14:00 | Pre-launch health check | DevOps |
| 16:00 | Enable maintenance mode | DevOps |

#### T-0 Day (Launch Day)
| Time | Task | Owner |
|------|------|-------|
| 00:00 | Final infrastructure check | DevOps |
| 00:15 | Verify all services operational | DevOps |
| 00:30 | Check database connectivity | DevOps |
| 00:45 | Check Redis connectivity | DevOps |
| 01:00 | Check queue workers | DevOps |
| 01:15 | Check monitoring systems | DevOps |
| 01:30 | Execute final database migration | DevOps |
| 01:45 | Clear all caches | DevOps |
| 02:00 | Warm up caches | DevOps |
| 02:15 | Switch to production release | DevOps |
| 02:30 | Verify deployment | DevOps |
| 02:45 | Run health checks | DevOps |
| 03:00 | Test critical functionality | QA Team |
| 03:15 | Verify monitoring working | DevOps |
| 03:30 | Disable maintenance mode | DevOps |
| 03:45 | Announce launch to internal team | Comms Team |
| 04:00 | Verify first customer can access | QA Team |
| 04:15 | Monitor for initial issues | All Teams |
| 04:30 | Begin 72-hour monitoring period | On-Call Team |

### Cutover Commands
```bash
# Enable maintenance mode
php artisan down --message="System maintenance. We'll be back soon." --retry=300

# Run final sync
php artisan legacy:sync --full --final

# Verify data integrity
php artisan legacy:verify --full

# Switch DNS (manual step - see DevOps runbook)
# Update CDN configuration (manual step)

# Disable maintenance mode
php artisan up

# Verify application health
curl -s http://localhost/health | jq .
```

---

## Phase 6: Post-Cutover Validation (T+1 to T+30 Days)

### Immediate Post-Cutover (T+1 to T+7 Days)
| Task | Frequency | Owner |
|------|-----------|-------|
| Monitor system health | Every 15 minutes | DevOps |
| Check error rates | Every hour | DevOps |
| Verify billing accuracy | Daily | Billing Team |
| Verify payment processing | Every transaction | Billing Team |
| Check customer access | Every hour | Support Team |
| Review logs for errors | Every 4 hours | DevOps |

### Short-Term (T+7 to T+14 Days)
| Task | Frequency | Owner |
|------|-----------|-------|
| Full data reconciliation | Daily | QA Team |
| Customer feedback review | Daily | Support Team |
| Performance monitoring | Continuous | DevOps |
| Payment reconciliation | Daily | Billing Team |
| RADIUS authentication test | Daily | NOC Team |

### Medium-Term (T+14 to T+30 Days)
| Task | Frequency | Owner |
|------|-----------|-------|
| Legacy system monitoring | Continuous | DevOps |
| Data consistency checks | Weekly | QA Team |
| Performance optimization | Continuous | DevOps |
| User training completion | By T+14 | Training Team |
| Legacy system decommission prep | By T+30 | DevOps |

### Legacy System Decommission (T+30 Days)
| Task | Owner | Notes |
|------|-------|-------|
| Verify no new data in legacy | QA Team | Final check |
| Take final backup of legacy | DevOps | Archive purposes |
| Disable legacy access | DevOps | Security |
| Shut down legacy servers | DevOps | Cost savings |
| Archive legacy backups | DevOps | Long-term storage |
| Update DNS to remove legacy | DevOps | Cleanup |

---

## Rollback Plan

### Rollback Triggers
| Severity | Trigger | Decision Maker |
|----------|---------|----------------|
| CRITICAL | > 5% of customers cannot access | CTO |
| CRITICAL | Billing data corruption | CTO |
| CRITICAL | Payment processing fails > 1 hour | CTO |
| HIGH | > 10% customer complaints | Engineering Manager |
| HIGH | Major feature not working | Engineering Manager |
| MEDIUM | Minor issues affecting < 5% customers | Support Lead |

### Rollback Types

#### Type 1: Fast Rollback (< 2 minutes)
**Trigger:** Application deployment issue, configuration error

**Steps:**
1. Revert to previous release (symlink switch)
2. Clear caches
3. Verify health
4. Communicate to users

**RTO:** 2 minutes  
**RPO:** 0 (no data loss)  
**Data Impact:** None

#### Type 2: Database Rollback (5-15 minutes)
**Trigger:** Database migration issue, data corruption

**Steps:**
1. Enable maintenance mode
2. Restore database from pre-migration backup
3. Re-run failed migrations (if safe)
4. Verify data integrity
5. Disable maintenance mode
6. Communicate to users

**RTO:** 15 minutes  
**RPO:** Up to 1 hour (depending on backup frequency)  
**Data Impact:** Minimal (data since last backup)

#### Type 3: Full Rollback (15-60 minutes)
**Trigger:** Critical system failure, unrecoverable error

**Steps:**
1. Enable maintenance mode
2. Restore full system from backup
3. Reconfigure to legacy system
4. Update DNS to point to legacy
5. Verify legacy system operational
6. Disable maintenance mode
7. Communicate to users

**RTO:** 60 minutes  
**RPO:** Up to 24 hours (depending on last full backup)  
**Data Impact:** Significant (data since migration start)

### Rollback Decision Matrix
| Scenario | Rollback Type | Approval Required | Communication |
|----------|---------------|-------------------|---------------|
| App config error | Type 1 | DevOps Lead | Internal only |
| Failed migration | Type 2 | Engineering Manager | All users |
| Data corruption | Type 2 | CTO | All users |
| Complete failure | Type 3 | CEO | All users + press |

---

## Data Migration Risks and Mitigations

### Risk Register
| Risk | Probability | Impact | Mitigation | Contingency |
|------|-------------|--------|------------|-------------|
| Data loss during migration | Low | Critical | Backup before each batch | Restore from backup |
| Data corruption | Low | Critical | Validate each batch | Rollback batch |
| Extended downtime | Medium | High | Use dual-run strategy | Extended maintenance window |
| Performance issues | Medium | High | Load test before migration | Scale up infrastructure |
| Data mapping errors | Medium | High | Manual review of mappings | Fix mappings, re-migrate |
| Legacy system changes | Medium | Medium | Freeze legacy changes | Pause migration, update mappings |
| User resistance | Medium | Medium | Training and communication | Extended support |

### Risk Mitigation Strategies
1. **Incremental Migration:** Migrate in small, verifiable batches
2. **Dual-Run Period:** Run both systems in parallel for validation
3. **Comprehensive Backups:** Backup before, during, and after migration
4. **Data Validation:** Automated and manual validation at each step
5. **Rollback Plan:** Well-documented and tested rollback procedures
6. **Communication:** Clear communication with all stakeholders

---

## Success Criteria

### Migration Success Metrics
| Metric | Target | Measurement |
|--------|--------|-------------|
| Data Accuracy | 100% | Record-by-record comparison |
| Data Completeness | 100% | Row count verification |
| Financial Reconciliation | 100% | Invoice/payment total matching |
| Customer Impact | < 1% | Support ticket volume |
| System Downtime | < 30 minutes | Monitoring data |
| Migration Duration | < 30 days | Calendar days |

### Go/No-Go Criteria

#### Go Criteria (All must be met)
- [ ] All historical data migrated and verified
- [ ] Delta sync running and healthy
- [ ] All parallel tests passed
- [ ] All stakeholders signed off
- [ ] Rollback plan tested and verified
- [ ] On-call team prepared
- [ ] Communication sent to customers

#### No-Go Criteria (Any one triggers delay)
- [ ] Data accuracy < 99.9%
- [ ] Delta sync failure rate > 1%
- [ ] Any critical bugs open
- [ ] Rollback plan untested
- [ ] On-call team not available
- [ ] Any stakeholders not signed off

---

## Communication Plan

### Stakeholder Communication
| Audience | Frequency | Channel | Owner |
|----------|-----------|---------|-------|
| Executive Team | Weekly | Email + Meeting | Project Manager |
| Engineering Team | Daily | Slack + Standup | DevOps Lead |
| Billing Team | Daily | Slack + Email | Billing Manager |
| Support Team | Daily | Slack + Training | Support Lead |
| NOC Team | Daily | Slack + Meeting | NOC Lead |
| Resellers | Weekly | Email | Sales Team |
| Customers | As needed | Email + SMS | Marketing Team |

### Customer Communication Timeline
| Time | Message | Channel | Audience |
|------|---------|---------|----------|
| T-30 | Migration announcement | Email | All customers |
| T-14 | Migration reminder | Email | All customers |
| T-7 | Service availability | Email + SMS | All customers |
| T-1 | Maintenance window | Email + SMS | All customers |
| T-0 | Launch announcement | Email + SMS | All customers |
| T+1 | Success confirmation | Email | All customers |

---

## Roles and Responsibilities

| Role | Responsibilities | Team |
|------|-------------------|------|
| Migration Lead | Overall migration planning and coordination | Engineering |
| Data Architect | Schema mapping, data transformation | Engineering |
| Database Engineer | Database migration, optimization | DevOps |
| QA Lead | Data validation, testing | QA |
| Billing Manager | Financial data validation | Billing |
| Support Lead | Customer communication, issue resolution | Support |
| NOC Lead | Network and RADIUS migration | NOC |
| DevOps Lead | Infrastructure, deployment, monitoring | DevOps |
| Project Manager | Timeline, stakeholder communication | PMO |

---

## Tools and Resources

### Migration Tools
| Tool | Purpose | Location |
|------|---------|----------|
| Laravel Artisan | Migration commands | app/Console/Commands/
| Migration Scripts | Data extraction and loading | database/migrations/legacy/
| Validation Scripts | Data integrity checks | database/migrations/legacy/verify/
| Monitoring Dashboard | Migration progress tracking | Grafana |

### Infrastructure Resources
| Resource | Purpose | Configuration |
|----------|---------|----------------|
| Migration Database | Staging for migration testing | Separate PostgreSQL instance |
| Migration Queue | Async migration processing | Redis queue |
| Migration Workers | Parallel migration processing | 4x Horizon workers |
| Backup Storage | Migration backups | S3 compatible storage |

---

## Timeline and Milestones

### Gantt Chart (Summary)
```
T-45  T-30  T-14  T-7   T-1   T+1   T+7   T+14  T+30
|----|----|----|----|----|----|----|----|----|--->
P1    P2   P2   P3   P4   P5    P6    P6    P6

P1 = Discovery & Planning
P2 = Historical Data Migration
P3 = Delta Sync Setup
P4 = Parallel Testing
P5 = Cutover
P6 = Post-Cutover Validation
```

### Key Milestones
| Milestone | Date | Description |
|----------|------|-------------|
| Migration Planning Complete | T-30 | All planning documents approved |
| Historical Migration Complete | T-14 | All historical data migrated |
| Delta Sync Live | T-14 | Real-time sync operational |
| Parallel Testing Complete | T-1 | All tests passed |
| Cutover Approved | T-1 | Go/No-Go decision |
| Production Launch | T-0 | System live |
| Post-Cutover Review | T+7 | Initial issues resolved |
| Migration Complete | T+30 | Legacy system decommissioned |

---

## Budget and Costs

### Estimated Costs
| Category | Estimated Cost | Notes |
|----------|----------------|-------|
| Engineering Time | 400 hours | 2 FTE for 5 weeks |
| DevOps Time | 160 hours | 1 FTE for 4 weeks |
| QA Time | 200 hours | 1 FTE for 5 weeks |
| Infrastructure | $2,000 | Additional servers for migration |
| Backup Storage | $500 | Additional storage for backups |
| Third-Party Tools | $0 | Using existing tools |
| **Total** | **$15,000** | Approximate |

### Cost Savings
| Category | Annual Savings | Notes |
|----------|----------------|-------|
| Legacy System Licenses | $12,000 | No longer needed |
| Legacy System Hosting | $6,000 | Server costs eliminated |
| Legacy System Maintenance | $24,000 | Engineering time saved |
| **Total** | **$42,000** | Annual savings |

---

## Lessons Learned (From Similar Projects)

1. **Start with a pilot migration** - Migrate a small subset first
2. **Validate early and often** - Don't wait until the end to validate
3. **Over-communicate** - Keep all stakeholders informed
4. **Have a rollback plan** - And test it before you need it
5. **Freeze legacy changes** - Prevent changes during migration
6. **Monitor performance** - Migration can impact production systems
7. **Train users early** - Don't wait until after migration

---

## Appendix: Data Mapping Details

### Customer Fields Mapping
```
Legacy: customers table
  ├─ customer_id -> uuid (new UUID)
  ├─ id -> id (auto-increment)
  ├─ first_name -> first_name
  ├─ last_name -> last_name
  ├─ email -> email
  ├─ phone -> phone
  ├─ mobile -> phone (if phone is null)
  ├─ address -> service_address
  ├─ city -> service_city
  ├─ state -> service_state
  ├─ zip -> service_postal_code
  ├─ country -> service_country
  ├─ billing_address -> billing_address
  ├─ billing_city -> billing_city
  ├─ billing_state -> billing_state
  ├─ billing_zip -> billing_postal_code
  ├─ billing_country -> billing_country
  ├─ dob -> date_of_birth
  ├─ gender -> gender
  ├─ nid -> nid_number (encrypted)
  ├─ status -> status (mapped)
  ├─ created_at -> created_at
  └─ updated_at -> updated_at
```

### Subscription Fields Mapping
```
Legacy: subscriptions table
  ├─ subscription_id -> uuid (new UUID)
  ├─ id -> id (auto-increment)
  ├─ customer_id -> customer_id (FK to migrated customer)
  ├─ package_id -> package_id (mapped to Fiberloop package)
  ├─ start_date -> start_date
  ├─ end_date -> end_date
  ├─ next_billing_date -> next_billing_date
  ├─ status -> status (mapped)
  ├─ monthly_charge -> monthly_price (converted to poysha)
  ├─ billing_cycle -> billing_cycle (mapped)
  └─ connection_type -> connection_type
```

---

## Document Control

**Version:** 1.0  
**Last Updated:** 2026-08-07  
**Next Review:** 2026-08-14  
**Owner:** Engineering Team  
**Approvers:** CTO, Engineering Manager, DevOps Lead, Billing Manager

---

## Related Documents

- [Production Launch Plan](../PRODUCTION_LAUNCH_PLAN.md)
- [Rollback Plan](../runbooks/ROLLBACK_PLAN.md)
- [Phase Verification Report](../PHASE_VERIFICATION.md)
