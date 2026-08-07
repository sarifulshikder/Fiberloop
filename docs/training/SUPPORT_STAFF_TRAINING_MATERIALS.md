# Fiberloop Support Staff Training Materials

**Document Version:** 1.0  
**Last Updated:** 2026-08-07  
**Status:** Ready for Training  
**Target Audience:** Support Agents, Support Leads, Billing Agents, Field Technicians

---

## Executive Summary

This document provides comprehensive training materials for Fiberloop support staff. This is a critical gate for Phase 19 (Production Launch Checklist) Task 7.

**Training Status:** ✅ MATERIALS COMPLETE - Ready for delivery

---

## Training Overview

### Training Goals
1. Familiarize support staff with Fiberloop system
2. Teach common customer workflows and troubleshooting
3. Ensure staff can handle customer inquiries effectively
4. Provide reference materials for ongoing support
5. Establish escalation procedures

### Training Structure
| Module | Duration | Format | Target Date |
|--------|----------|--------|-------------|
| Module 1: System Overview | 2 hours | Instructor-led | T-6 Days |
| Module 2: Customer Management | 2 hours | Instructor-led + Hands-on | T-6 Days |
| Module 3: Billing & Invoicing | 2 hours | Instructor-led + Hands-on | T-5 Days |
| Module 4: Payment Processing | 2 hours | Instructor-led + Hands-on | T-5 Days |
| Module 5: Network & RADIUS | 2 hours | Instructor-led + Demo | T-4 Days |
| Module 6: Ticketing & Support | 2 hours | Instructor-led + Hands-on | T-4 Days |
| Module 7: Troubleshooting | 2 hours | Instructor-led + Hands-on | T-3 Days |
| Hands-on Practice | 4 hours | Lab | T-3 Days |
| Assessment | 1 hour | Written + Practical | T-2 Days |

### Training Delivery Methods
1. **Instructor-Led Training:** Classroom sessions with live demos
2. **Hands-on Labs:** Practical exercises in staging environment
3. **Video Tutorials:** Recorded sessions for reference
4. **Documentation:** Quick reference guides and manuals
5. **Q&A Sessions:** Open forums for questions
6. **Shadowing:** Observe experienced staff
7. **Mentoring:** One-on-one coaching

---

## Module 1: System Overview

### Objectives
- Understand Fiberloop architecture and components
- Learn about different user types and permissions
- Understand data flow in the system
- Familiarize with the admin panel (Filament)

### Agenda
| Time | Topic | Format | Materials |
|------|-------|--------|-----------|
| 0:00-0:15 | Introduction to Fiberloop | Lecture | Slides |
| 0:15-0:45 | System Architecture | Lecture + Diagram | Architecture Diagram |
| 0:45-1:15 | User Types and Roles | Lecture + Demo | Role Matrix |
| 1:15-1:30 | Break | | |
| 1:30-1:45 | Data Flow | Lecture | Data Flow Diagram |
| 1:45-2:00 | Admin Panel Overview | Live Demo | Admin Credentials |

### Content

#### 1.1 Introduction to Fiberloop
- What is Fiberloop?
  - ISP billing and subscriber management platform
  - Built for 100,000+ subscriber scale
  - Manages customers, packages, billing, payments, RADIUS, resellers
- Key Features:
  - Customer CRM with KYC
  - Package and pricing engine
  - Automated billing and invoicing
  - Multiple payment gateway integration
  - FreeRADIUS AAA integration
  - Network device monitoring
  - Reseller/franchise management
  - Ticketing and field operations
  - Customer self-service portal
  - AI-assisted analytics

#### 1.2 System Architecture
```
┌─────────────────────────────────────────────────────────────────┐
│                        Fiberloop Architecture                        │
├─────────────────┬─────────────────┬─────────────────┬────────────┤
│   Frontend       │   Backend        │   Database        │  Services   │
│  └─ Filament     │  └─ Laravel 13   │  └─ PostgreSQL 18 │  └─ Redis   │
│  └─ Customer App │  └─ Octane/FF    │  └─ FreeRADIUS    │  └─ Horizon  │
│  └─ Web Portal   │  └─ Reverb       │  └─ S3 Storage    │  └─ Elastic  │
└─────────────────┴─────────────────┴─────────────────┴────────────┘
```

**Components:**
- **Frontend:** Filament Admin Panel (v5), Customer Web Portal, Flutter Mobile App
- **Backend:** Laravel 13, PHP 8.4+, Laravel Octane (FrankenPHP)
- **Database:** PostgreSQL 18 (with FreeRADIUS schema)
- **Cache/Queue:** Redis with Laravel Horizon
- **Realtime:** Laravel Reverb
- **Monitoring:** Prometheus, Grafana, ELK Stack

#### 1.3 User Types and Roles

**Roles in Fiberloop:**
| Role | Description | Access Level | Typical Users |
|------|-------------|--------------|---------------|
| super_admin | Full system access | All | CTO, System Admin |
| admin | Full admin access | All except system settings | Managers |
| noc_engineer | Network operations | Network, Devices, RADIUS | NOC Team |
| support_agent | Customer support | Customers, Tickets, Payments | Support Team |
| billing_agent | Billing operations | Billing, Invoices, Payments | Billing Team |
| reseller | Reseller access | Own customers, billing | Reseller Staff |
| field_technician | Field operations | Tickets, Field Jobs | Technicians |
| customer | Customer access | Own data via portal/app | End Users |

**Permission Matrix (Key Permissions):**
| Action | super_admin | admin | noc_engineer | support_agent | billing_agent | reseller | field_technician | customer |
|--------|-------------|-------|--------------|---------------|---------------|---------|-----------------|----------|
| View Customers | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (own) | ✅ (assigned) | ✅ (own) |
| Create Customer | ✅ | ✅ | ❌ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Edit Customer | ✅ | ✅ | ❌ | ✅ | ❌ | ✅ (own) | ❌ | ✅ (own) |
| View KYC | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ (own) | ❌ | ✅ (own) |
| View Invoices | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ (own) | ❌ | ✅ (own) |
| Create Invoice | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Process Payment | ✅ | ✅ | ❌ | ✅ | ✅ | ✅ (own) | ❌ | ✅ (own) |
| View RADIUS | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Manage Devices | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ |
| View Tickets | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ (own) | ✅ | ✅ (own) |
| Create Ticket | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

#### 1.4 Data Flow

**Customer Journey:**
```
Lead → Site Survey → Customer Creation → Package Selection → 
Subscription Activation → RADIUS Provisioning → Billing → 
Payment → Service Delivery → Support
```

**Billing Flow:**
```
Subscription Active → Billing Run (Monthly) → Invoice Generated → 
Invoice Sent → Payment Received/Due → 
Payment Processed → Receipt Sent → 
If Unpaid: Suspension Warning → Suspension → Reactivation on Payment
```

#### 1.5 Admin Panel Overview

**Filament v5 Admin Panel:**
- **URL:** `/admin`
- **Authentication:** Staff login (not customer login)
- **Dashboard:** Overview with widgets and statistics
- **Navigation:** Left sidebar with resource groups

**Resource Groups:**
- **Customers:** Customer management, leads, surveys
- **Billing:** Invoices, payments, credit notes, refunds
- **Packages:** Package management, promotions, add-ons
- **Network:** Devices, OLT/ONU, RADIUS, IP Pools
- **Resellers:** Reseller management, commissions, approvals
- **Support:** Tickets, field jobs, SLA management
- **Reports:** Dashboards, analytics, exports
- **Settings:** Users, roles, permissions, system config

---

## Module 2: Customer Management

### Objectives
- Learn how to create, view, edit, and manage customers
- Understand customer status lifecycle
- Learn how to handle KYC documents
- Understand customer notes and timeline
- Learn bulk actions

### Agenda
| Time | Topic | Format | Materials |
|------|-------|--------|-----------|
| 0:00-0:30 | Customer Creation | Demo + Hands-on | Customer Form |
| 0:30-1:00 | Customer View/Edit | Demo + Hands-on | Customer Resource |
| 1:00-1:15 | Customer Status Management | Lecture + Demo | Status Flow |
| 1:15-1:30 | Break | | |
| 1:30-1:45 | KYC Document Handling | Demo | KYC Service |
| 1:45-2:00 | Bulk Actions & Export | Demo + Hands-on | Bulk Actions |

### Content

#### 2.1 Customer Creation

**Required Fields:**
- First Name, Last Name
- Phone (E.164 format: +880XXXXXXXXXX)
- Email
- Service Address
- Package
- Connection Type (PPPoE, Hotspot, Static IP)
- Billing Cycle

**Optional Fields:**
- Date of Birth
- Gender
- NID Number
- KYC Documents (front/back photos, signature)
- Billing Address (if different from service)
- Notes

**Creation Methods:**
1. **Manual via Filament:** Use CustomerResource
2. **API:** POST `/api/customers`
3. **Import:** CSV import via artisan command
4. **Self-Registration:** Customer portal signup

**Demo: Creating a Customer**
```
Steps:
1. Navigate to /admin/customers
2. Click "Create Customer" button
3. Fill in all required fields
4. Upload KYC documents if available
5. Select appropriate package
6. Set connection type
7. Add any notes
8. Click "Save"
```

#### 2.2 Customer View/Edit

**Customer Detail Page:**
- **Profile Tab:** Basic information, contact details
- **Subscriptions Tab:** Active and historical subscriptions
- **Invoices Tab:** All invoices with payment status
- **Payments Tab:** All payments with receipts
- **Tickets Tab:** All support tickets
- **Notes Tab:** Timeline of all customer interactions
- **KYC Tab:** KYC documents (access restricted)
- **Activity Tab:** Audit log of all changes

**Quick Actions:**
- Suspend Customer
- Reactivate Customer
- Terminate Customer
- Send SMS
- Send Email
- Create Ticket
- Create Invoice

#### 2.3 Customer Status Lifecycle

**Status Flow:**
```
                          ┌─────────────────┐
                          │     pending      │
                          └──────────┬──────┘
                                     │
                     ┌───────────────┼───────────────┐
                     │               │               │
                     ▼               ▼               ▼
              ┌──────────┐    ┌──────────┐    ┌──────────┐
              │  active   │    │ suspended│    │terminated│
              └────┬─────┘    └────┬─────┘    └──────────┘
                   │                 │
           ┌───────┴───────┐   ┌─────┴─────┐
           │               │   │           │
           ▼               ▼   ▼           ▼
    ┌──────────┐    ┌──────────┐    └────────┘
    │ expired  │    │ cancelled │     (end)
    └──────────┘    └──────────┘
```

**Allowed Transitions:**
| From | To | Trigger | Reversible |
|------|----|---------|-------------|
| pending | active | Customer activation | Yes |
| pending | cancelled | Request cancellation | No |
| active | suspended | Overdue payment | Yes |
| active | terminated | Manual termination | No |
| active | expired | End of contract | Yes |
| suspended | active | Payment received | Yes |
| suspended | terminated | Manual termination | No |
| terminated | active | Reactivation request | Yes (manual) |

#### 2.4 KYC Document Handling

**KYC Access Rules:**
- Only users with `view_kyc` permission can access KYC tab
- KYC documents are encrypted at rest
- Access logged in activity log
- Signed URLs for temporary access

**Supported KYC Documents:**
- NID Front Photo
- NID Back Photo
- Passport
- Driving License
- Signature Photo

**Handling KYC Requests:**
1. Verify customer identity
2. Request missing documents via email/SMS
3. Upload documents to customer record
4. Mark as verified in system

#### 2.5 Bulk Actions

**Available Bulk Actions:**
- Suspend Selected Customers
- Reactivate Selected Customers
- Send SMS to Selected
- Send Email to Selected
- Export Selected to CSV
- Delete Selected (soft delete)

**Demo: Bulk Suspension**
```
Steps:
1. Navigate to /admin/customers
2. Use filters to find overdue customers
3. Select customers using checkboxes
4. Click "Actions" dropdown
5. Select "Suspend Selected"
6. Add suspension reason
7. Confirm action
```

---

## Module 3: Billing & Invoicing

### Objectives
- Understand billing cycles and invoicing
- Learn how to view and manage invoices
- Understand proration calculation
- Learn about tax handling
- Understand invoice PDF generation

### Agenda
| Time | Topic | Format | Materials |
|------|-------|--------|-----------|
| 0:00-0:30 | Billing Cycle Overview | Lecture | Billing Flow |
| 0:30-1:00 | Invoice Management | Demo + Hands-on | Invoice Resource |
| 1:00-1:15 | Proration | Lecture + Examples | Proration Service |
| 1:15-1:30 | Break | | |
| 1:30-1:45 | Tax Handling | Lecture | Tax Rate Config |
| 1:45-2:00 | Invoice PDF & Sending | Demo + Hands-on | Invoice Actions |

### Content

#### 3.1 Billing Cycle Overview

**Billing Cycles:**
- **Monthly:** Most common, billed on same day each month
- **Quarterly:** Billed every 3 months
- **Yearly:** Billed annually
- **Prepaid:** Pay before service period
- **Postpaid:** Pay after service period

**Billing Run Process:**
```
1. System checks for subscriptions due for billing
2. For each subscription:
   a. Calculate billing period
   b. Apply proration if needed
   c. Calculate base amount
   d. Add taxes
   e. Apply discounts/promotions
   f. Generate invoice
3. Send invoice notifications
4. Queue for payment processing
```

**Manual Billing Run:**
```bash
# Run billing for all due subscriptions
php artisan billing:run

# Run billing for specific customer
php artisan billing:run --customer=123

# Dry run (no actual invoices)
php artisan billing:run --dry-run
```

#### 3.2 Invoice Management

**Invoice Statuses:**
| Status | Description | Actions Available |
|--------|-------------|------------------|
| draft | Invoice created, not finalized | Edit, Delete, Finalize |
| sent | Invoice sent to customer | View, Download, Mark as Paid |
| paid | Invoice paid in full | View, Download, Refund |
| partial | Partial payment received | View, Download, Record Payment |
| overdue | Payment due date passed | View, Download, Send Reminder |
| void | Invoice cancelled | View, Download |

**Invoice Actions:**
- **View Invoice:** See full details including line items
- **Download PDF:** Generate and download PDF
- **Send Email:** Resend invoice to customer
- **Send SMS:** Send SMS notification with due amount
- **Mark as Paid:** Record manual payment
- **Record Partial Payment:** Record partial payment
- **Void Invoice:** Cancel invoice
- **Create Credit Note:** Issue refund/credit

#### 3.3 Proration

**Proration Scenarios:**
1. **Mid-Cycle Upgrade:** Charge difference for remaining period
2. **Mid-Cycle Downgrade:** Credit difference for remaining period
3. **Early Activation:** Charge for partial month
4. **Late Activation:** Credit for unused days

**Proration Calculation:**
```
Proration Amount = (Monthly Price / Days in Month) × Days Used
```

**Example:**
- Package: 1000 BDT/month
- Activated: 15th of 30-day month
- Days Used: 16 days (15-30 inclusive)
- Proration: (1000 / 30) × 16 = 533.33 BDT

#### 3.4 Tax Handling

**Tax Configuration:**
- **Tax Rates:** Configured per tenant or globally
- **Default Rate:** 15% VAT
- **Tax Calculation:** Applied to invoice subtotal

**Tax Calculation:**
```
Subtotal: 1000.00 BDT
Tax (15%): 150.00 BDT
Total: 1150.00 BDT
```

**Invoice with Tax:**
```
Invoice #: INV-2026-0001
Date: 2026-08-01
Due Date: 2026-08-16

Description | Quantity | Unit Price | Amount
-----------|----------|------------|--------
Internet Package | 1 | 1000.00 | 1000.00
Subtotal | | | 1000.00
Tax (15%) | | | 150.00
Total Due | | | 1150.00
```

---

## Module 4: Payment Processing

### Objectives
- Learn payment gateway integration
- Understand payment recording
- Learn about payment reconciliation
- Understand partial and split payments
- Learn about idempotency
- Understand refund processing
- Learn about wallet/prepaid balance

### Agenda
| Time | Topic | Format | Materials |
|------|-------|--------|-----------|
| 0:00-0:30 | Payment Gateways | Lecture | Gateway Config |
| 0:30-1:00 | Payment Recording | Demo + Hands-on | Payment Resource |
| 1:00-1:15 | Break | | |
| 1:15-1:30 | Payment Reconciliation | Lecture | Reconciliation Job |
| 1:30-1:45 | Partial & Split Payments | Lecture + Demo | Payment Logic |
| 1:45-2:00 | Refunds & Wallet | Demo + Hands-on | Refund Flow |

### Content

#### 4.1 Payment Gateways

**Supported Gateways:**
| Gateway | Type | Transaction Fee | Settlement | Status |
|---------|------|----------------|------------|--------|
| bKash | Mobile Wallet | 1.85% | T+1 | ✅ Active |
| Nagad | Mobile Wallet | 1.80% | T+1 | ✅ Active |
| SSLCommerz | Card | 2.85% | T+2 | ✅ Active |
| Cash | Manual | 0% | Immediate | ✅ Active |
| Bank Transfer | Manual | 0% | T+1 | ✅ Active |

**Webhook Configuration:**
- Each gateway has webhook endpoint
- Signature verification for security
- Automatic payment recording on webhook

#### 4.2 Payment Recording

**Payment Statuses:**
| Status | Description | Next Action |
|--------|-------------|-------------|
| pending | Payment initiated, not confirmed | Wait for webhook |
| completed | Payment confirmed | Mark invoice as paid |
| failed | Payment failed | Notify customer, retry |
| refunded | Payment refunded | Create credit note |
| cancelled | Payment cancelled | Notify customer |

**Recording Manual Payment:**
```
Steps:
1. Navigate to /admin/payments
2. Click "Create Payment"
3. Select customer
4. Select invoice(s) to pay
5. Enter amount
6. Select payment gateway (cash, bank_transfer, etc.)
7. Enter transaction reference
8. Enter received by (field agent name)
9. Click "Save"
```

#### 4.3 Payment Reconciliation

**Reconciliation Process:**
1. Download settlement reports from gateways
2. Match payments in system with settlement report
3. Flag discrepancies for investigation
4. Resolve discrepancies
5. Generate reconciliation report

**Automated Reconciliation:**
```bash
# Run daily reconciliation
php artisan payments:reconcile

# Check for discrepancies
php artisan payments:reconcile --check-discrepancies

# Generate reconciliation report
php artisan payments:reconcile --report
```

#### 4.4 Partial and Split Payments

**Partial Payment:**
- Customer pays less than invoice total
- System allocates to oldest outstanding invoice first
- Creates partial payment record
- Invoice remains in "partial" status

**Split Payment:**
- Single payment covers multiple invoices
- System allocates proportionally or by priority
- Creates multiple payment records linked via `split_from_payment_id`

**Allocation Strategy:**
1. **Oldest Invoice First:** Pay oldest outstanding invoice first
2. **Highest Priority:** Pay invoices closest to due date
3. **Manual Override:** Support can manually allocate

#### 4.5 Refund Processing

**Refund Types:**
1. **Full Refund:** Refund entire payment
2. **Partial Refund:** Refund portion of payment
3. **Credit Note:** Issue credit for future use

**Refund Flow:**
```
Customer Request → Support Verification → 
Refund Approval → Process Refund → 
Create Credit Note → Notify Customer
```

**Processing Refund:**
```
Steps:
1. Navigate to /admin/credit-notes
2. Click "Create Credit Note"
3. Select customer and original invoice
4. Enter refund amount and reason
5. Select refund method (original gateway, cash, etc.)
6. Submit for approval
7. Process refund through gateway (if applicable)
8. Notify customer
```

#### 4.6 Wallet/Prepaid Balance

**Wallet Features:**
- Customers can top up balance
- Balance used for automatic invoice payment
- Balance can be used for package upgrades
- Transaction history maintained

**Wallet Actions:**
- **Top Up:** Add funds to wallet via payment gateway
- **Auto-Pay:** Enable automatic payment from wallet
- **Manual Deduction:** Deduct for invoices or services
- **Refund to Wallet:** Refund payments to wallet instead of original gateway

---

## Module 5: Network & RADIUS

### Objectives
- Understand RADIUS authentication flow
- Learn network device management
- Understand OLT/ONU monitoring
- Learn session management
- Understand FUP enforcement

### Agenda
| Time | Topic | Format | Materials |
|------|-------|--------|-----------|
| 0:00-0:30 | RADIUS Authentication Flow | Lecture | RADIUS Diagram |
| 0:30-1:00 | Network Device Management | Demo | NetworkDevice Resource |
| 1:00-1:15 | Break | | |
| 1:15-1:30 | OLT/ONU Monitoring | Demo | OLT/ONU Resources |
| 1:30-1:45 | Session Management | Demo | LiveRadiusSessions |
| 1:45-2:00 | FUP Enforcement | Lecture + Demo | FUP Job |

### Content

#### 5.1 RADIUS Authentication Flow

**PPPoE Flow:**
```
Customer Device → NAS (MikroTik/OLT) → RADIUS Server
                                   ↓
                            Check radcheck table
                                   ↓
                 ┌─────────────────────────────────────┐
                 │ Is username/password valid?            │
                 └──────────────────┬──────────────────┘
                                    │
              ┌─────────────────────┴─────────────────────┐
              │                                       │
              ▼                                       ▼
    ✅ Access-Accept                        ❌ Access-Reject
    (with attributes)                      (authentication failed)
```

**Hotspot Flow:**
```
Customer Device → Hotspot NAS → RADIUS Server
                                    ↓
                             Check radcheck table
                                    ↓
                    Return Access-Accept with:
                    - Session-Timeout
                    - Idle-Timeout
                    - Mikrotik-Rate-Limit
```

**RADIUS Attributes Used:**
| Attribute | Purpose | Example |
|-----------|---------|---------|
| Cleartext-Password | Password | user123 |
| Mikrotik-Rate-Limit | Bandwidth limit | 10M/5M |
| Session-Timeout | Session duration | 86400 (24h) |
| Idle-Timeout | Idle timeout | 300 (5min) |
| Framed-IP-Address | Static IP | 192.168.1.100 |
| NAS-Port | Port information | PPPoE-1 |

#### 5.2 Network Device Management

**Device Types:**
- **OLT (Optical Line Terminal):** VSOL, BDCOM, Huawei, ZTE
- **ONU (Optical Network Unit):** Customer premise equipment
- **Switch:** Network switches
- **Router:** Core and edge routers
- **Access Point:** Wireless access points

**Device Management:**
1. **Add Device:** IP, credentials, type, location
2. **Poll Metrics:** SNMP polling every 5 minutes
3. **Monitor Status:** CPU, memory, uptime, interfaces
4. **Configure:** Push configuration changes
5. **Alerts:** Set up threshold alerts

#### 5.3 OLT/ONU Monitoring

**OLT Monitoring:**
- Optical signal levels (dBm)
- ONU registration status
- Port status and errors
- CPU and memory usage

**ONU Monitoring:**
- Signal strength (RX/TX power)
- Online/offline status
- Registration status
- Distance from OLT

**Optical Signal Thresholds:**
| Metric | Normal Range | Warning | Critical |
|--------|--------------|---------|----------|
| RX Power | -23 to -8 dBm | -25 to -23 or -8 to -6 | < -25 or > -6 |
| TX Power | -8 to -3 dBm | -10 to -8 or -3 to -1 | < -10 or > -1 |
| OLT Temp | < 60°C | 60-70°C | > 70°C |

#### 5.4 Session Management

**Live Sessions Page:**
- **URL:** `/admin/network/live-sessions`
- **Auto-refresh:** Every 30 seconds
- **Columns:** Username, IP, MAC, NAS, Port, Start Time, Data In/Out
- **Actions:** Disconnect, View Details, Filter

**Session Actions:**
- **View Details:** Full session information
- **Disconnect:** Force disconnect user (CoA request)
- **Change Bandwidth:** Update rate limit
- **Suspend:** Suspend user session

**Disconnect User:**
```bash
# Send CoA disconnect request
php artisan radius:disconnect username user123

# Disconnect all sessions for customer
php artisan radius:disconnect --customer=123
```

#### 5.5 FUP Enforcement

**FUP (Fair Usage Policy):**
- **Threshold:** Defined per package (e.g., 100GB)
- **Throttled Speed:** Defined per package (e.g., 10Mbps down, 5Mbps up)
- **Reset Cycle:** Monthly on billing date

**FUP Enforcement Process:**
```
1. Every 30 minutes, check radacct for data usage
2. For each active subscription:
   a. Calculate data used (upload + download)
   b. Check if exceeds FUP threshold
   c. If exceeded, apply throttled speed via RADIUS
3. On billing date, reset FUP counters
```

**Manual FUP Check:**
```bash
# Check FUP status for all customers
php artisan radius:enforce-fup

# Check FUP status for specific customer
php artisan radius:enforce-fup --customer=123

# Reset FUP for all customers
php artisan radius:reset-fup
```

---

## Module 6: Ticketing & Support

### Objectives
- Learn ticket creation and management
- Understand SLA tracking
- Learn auto-correlation with incidents
- Learn technician dispatch
- Understand customer communication
- Learn escalation procedures

### Agenda
| Time | Topic | Format | Materials |
|------|-------|--------|-----------|
| 0:00-0:30 | Ticket Creation & Management | Demo + Hands-on | Ticket Resource |
| 0:30-1:00 | SLA Tracking | Lecture + Demo | SLA Service |
| 1:00-1:15 | Break | | |
| 1:15-1:30 | Incident Correlation | Demo | Incident Resource |
| 1:30-1:45 | Technician Dispatch | Demo | FieldJob Resource |
| 1:45-2:00 | Escalation Procedures | Lecture + Role Play | Escalation Matrix |

### Content

#### 6.1 Ticket Creation & Management

**Ticket Types:**
| Type | Description | Priority | SLA |
|------|-------------|----------|-----|
| Technical | Network/connection issues | HIGH | 4 hours |
| Billing | Invoice/payment issues | MEDIUM | 8 hours |
| Sales | Package/sales inquiries | LOW | 24 hours |
| General | Other inquiries | LOW | 24 hours |
| Complaint | Customer complaints | HIGH | 4 hours |

**Ticket Statuses:**
| Status | Description | Next State |
|--------|-------------|------------|
| open | New ticket, not assigned | assigned, resolved |
| assigned | Assigned to agent | in_progress, resolved |
| in_progress | Agent working on ticket | on_hold, resolved |
| on_hold | Waiting for customer/third party | in_progress |
| resolved | Issue resolved | closed, reopened |
| closed | Ticket closed | reopened |
| reopened | Reopened after closure | assigned |

**Creating a Ticket:**
```
Steps:
1. Navigate to /admin/tickets
2. Click "Create Ticket"
3. Select customer (or create as guest)
4. Select type and priority
5. Enter subject and description
6. Add internal notes (not visible to customer)
7. Assign to agent (optional)
8. Click "Save"
```

**Ticket Fields:**
- **Customer:** Required, links to customer record
- **Type:** Technical, Billing, Sales, General, Complaint
- **Priority:** Low, Medium, High, Critical
- **Status:** Open, Assigned, In Progress, On Hold, Resolved, Closed
- **Subject:** Brief description
- **Description:** Detailed issue description
- **Internal Notes:** For staff only, not visible to customer
- **Public Comments:** Visible to customer
- **Assigned To:** Staff member responsible
- **Category:** Further classification
- **Tags:** For filtering and reporting

#### 6.2 SLA Tracking

**SLA Configuration:**
| Priority | Response Time | Resolution Time | Business Hours |
|----------|---------------|-----------------|----------------|
| Critical | 15 minutes | 1 hour | 24/7 |
| High | 30 minutes | 4 hours | Business hours |
| Medium | 1 hour | 8 hours | Business hours |
| Low | 4 hours | 24 hours | Business hours |

**SLA Calculation:**
- **Response Time:** Time from ticket creation to first agent response
- **Resolution Time:** Time from ticket creation to resolution
- **Business Hours:** 09:00-18:00, Monday-Friday (excludes weekends and holidays)

**SLA Breach Handling:**
1. System automatically flags SLA breaches
2. Escalates to support lead after 50% of SLA time
3. Escalates to manager after 75% of SLA time
4. Sends alert to on-call after 100% of SLA time

**Check SLA Breaches:**
```bash
# Check for SLA breaches
php artisan tickets:check-sla

# Notify on SLA breach
php artisan tickets:notify-sla-breach
```

#### 6.3 Incident Correlation

**Auto-Correlation:**
- System automatically links tickets to incidents
- Based on: keywords, customer location, device, time
- Helps identify widespread issues

**Incident Types:**
| Type | Description | Severity |
|------|-------------|----------|
| Network Outage | Complete network failure | CRITICAL |
| Partial Outage | Some customers affected | HIGH |
| Service Degradation | Slow performance | MEDIUM |
| Maintenance | Planned maintenance | LOW |

**Incident Lifecycle:**
```
Detected → Identified → Investigating → 
Resolved → Recovery → Closed
```

**Viewing Incidents:**
- **URL:** `/admin/network/incidents`
- **Fields:** Title, Type, Severity, Status, Affected Customers, Start Time
- **Actions:** View Details, Update Status, Link Tickets, Add Notes

#### 6.4 Technician Dispatch

**Field Job Creation:**
1. From ticket: Click "Dispatch Technician"
2. Select technician or "Any Available"
3. Set priority and estimated time
4. Add job description
5. Assign required equipment
6. Set customer address
7. Save and notify technician

**Field Job Statuses:**
| Status | Description | Next State |
|--------|-------------|------------|
| pending | Job created, not assigned | assigned |
| assigned | Assigned to technician | in_progress, cancelled |
| in_progress | Technician working on job | on_hold, completed |
| on_hold | Waiting for parts/customer | in_progress |
| completed | Job completed | verified, closed |
| cancelled | Job cancelled | closed |
| verified | Customer verified completion | closed |
| closed | Job archived | - |

**Technician Actions:**
- **View Job:** See job details, customer info, address
- **Accept Job:** Confirm assignment
- **Update Status:** Change job status
- **Add Notes:** Update with progress
- **Complete Job:** Mark as completed with resolution
- **Request Parts:** Order required equipment

#### 6.5 Escalation Procedures

**Escalation Matrix:**
| Issue | First Level | Second Level | Third Level |
|-------|------------|--------------|-------------|
| Technical Issue | Support Agent | Support Lead | NOC Engineer |
| Billing Issue | Support Agent | Billing Agent | Billing Manager |
| Network Issue | Support Agent | NOC Engineer | DevOps |
| Payment Issue | Billing Agent | Billing Manager | Finance |
| Refund Request | Billing Agent | Billing Manager | CTO |

**Escalation Path:**
```
Support Agent → Support Lead → NOC Engineer/DevOps → 
Engineering Manager → CTO → CEO
```

**Escalation Criteria:**
- **Time-Based:** Ticket open > 50% of SLA time
- **Complexity:** Issue beyond agent's expertise
- **Customer Request:** Customer explicitly requests escalation
- **Recurring Issue:** Same issue reported multiple times
- **High Impact:** Affects multiple customers

**Escalating a Ticket:**
```
Steps:
1. Click "Escalate" button on ticket
2. Select escalation reason
3. Select next level (person or role)
4. Add escalation notes
5. System notifies next level
6. Original agent remains CC'd on ticket
```

---

## Module 7: Troubleshooting

### Objectives
- Learn common issues and resolutions
- Understand health check interpretation
- Learn log analysis techniques
- Understand error handling
- Learn escalation paths

### Agenda
| Time | Topic | Format | Materials |
|------|-------|--------|-----------|
| 0:00-0:45 | Common Issues & Resolutions | Lecture + Demo | Troubleshooting Guide |
| 0:45-1:00 | Health Check Interpretation | Demo | Health Endpoints |
| 1:00-1:15 | Break | | |
| 1:15-1:30 | Log Analysis | Demo + Hands-on | Log Files |
| 1:30-1:45 | Error Handling | Lecture + Demo | Error Codes |
| 1:45-2:00 | Escalation Paths | Role Play | Escalation Matrix |

### Content

#### 7.1 Common Issues & Resolutions

**Login Issues:**
| Symptom | Possible Cause | Resolution |
|---------|---------------|------------|
| Cannot login to admin | Wrong credentials | Reset password |
| Cannot login to admin | Account suspended | Reactivate account |
| Cannot login to admin | 2FA not set up | Complete 2FA setup |
| Cannot login to customer portal | Wrong credentials | Customer password reset |
| Cannot login to customer portal | Account not activated | Activate customer |
| Cannot login to customer portal | Subscription expired | Renew subscription |

**Billing Issues:**
| Symptom | Possible Cause | Resolution |
|---------|---------------|------------|
| Invoice not generated | Billing run not executed | Run manually |
| Invoice amount incorrect | Proration error | Recalculate invoice |
| Invoice not sent | Email/SMS issue | Resend manually |
| Invoice already paid | Duplicate payment | Check payment records |
| Cannot pay invoice | Gateway down | Use alternative gateway |

**Payment Issues:**
| Symptom | Possible Cause | Resolution |
|---------|---------------|------------|
| Payment not recorded | Webhook failed | Manual recording |
| Payment failed | Insufficient balance | Customer to top up |
| Payment refunded | Customer request | Process refund |
| Duplicate payment | Retry after failure | Refund duplicate |
| Payment not showing | Sync delay | Wait, then check |

**Connection Issues:**
| Symptom | Possible Cause | Resolution |
|---------|---------------|------------|
| Cannot connect | Authentication failed | Check RADIUS |
| Slow speed | FUP enforced | Check usage |
| No internet | NAS down | Check device |
| Frequent disconnects | Session timeout | Check NAS config |
| IP not assigned | DHCP issue | Check DHCP server |

**RADIUS Issues:**
| Symptom | Possible Cause | Resolution |
|---------|---------------|------------|
| Access-Reject | Wrong username/password | Verify credentials |
| Access-Reject | Account suspended | Reactivate customer |
| Access-Reject | Package not assigned | Assign package |
| Access-Reject | NAS not configured | Add NAS to system |
| No response | RADIUS down | Restart RADIUS |

#### 7.2 Health Check Interpretation

**Health Endpoints:**
- `/health` - Comprehensive health check
- `/health/ping` - Simple ping
- `/metrics` - Prometheus metrics

**Health Check Response:**
```json
{
  "status": "healthy",
  "timestamp": "2026-08-07T10:00:00Z",
  "checks": {
    "database": {
      "status": "up",
      "response_time": 2,
      "last_check": "2026-08-07T10:00:00Z"
    },
    "redis": {
      "status": "up",
      "response_time": 1,
      "last_check": "2026-08-07T10:00:00Z"
    },
    "radius": {
      "status": "up",
      "response_time": 50,
      "last_check": "2026-08-07T10:00:00Z"
    },
    "queue": {
      "status": "up",
      "queue_size": 10,
      "failed_jobs": 0
    }
  },
  "version": "1.0.0",
  "environment": "production"
}
```

**Troubleshooting Health Checks:**
1. Check `/health` endpoint
2. Identify failed checks
3. Check specific service health
4. Review logs for errors
5. Restart failed service if needed

#### 7.3 Log Analysis

**Log Locations:**
| Log | Location | Rotation | Retention |
|-----|----------|----------|-----------|
| Application | `/storage/logs/laravel.log` | Daily | 30 days |
| Queue | `/storage/logs/horizon.log` | Daily | 30 days |
| RADIUS | `/var/log/freeradius/radius.log` | Daily | 7 days |
| Nginx | `/var/log/nginx/access.log` | Daily | 30 days |
| Database | PostgreSQL logs | Daily | 7 days |

**Log Levels:**
| Level | Color | Usage | Severity |
|-------|-------|-------|----------|
| EMERGENCY | Red | System unusable | Critical |
| ALERT | Red | Immediate action needed | Critical |
| CRITICAL | Red | Critical conditions | Critical |
| ERROR | Red | Error conditions | High |
| WARNING | Yellow | Warning conditions | Medium |
| NOTICE | Blue | Normal but significant | Low |
| INFO | Green | Informational | Low |
| DEBUG | Gray | Debug information | Low |

**Common Log Patterns:**
```
# Database query error
[2026-08-07 10:00:00] local.ERROR: SQLSTATE[23000]: Integrity constraint violation 

# Payment gateway error
[2026-08-07 10:00:00] local.ERROR: bKash payment failed: Insufficient balance 

# RADIUS authentication failure
[2026-08-07 10:00:00] radius.INFO: Access-Reject for user123: Password mismatch 

# Queue job failed
[2026-08-07 10:00:00] local.ERROR: Job App\Jobs\GenerateInvoices failed: Database timeout 
```

**Log Analysis Tools:**
```bash
# View recent logs
tail -f /storage/logs/laravel.log

# Search for errors
grep -i error /storage/logs/laravel.log | tail -20

# Search by customer
grep "customer_id=123" /storage/logs/laravel.log

# Search by date
grep "2026-08-07" /storage/logs/laravel.log

# Count errors
grep -c "ERROR" /storage/logs/laravel.log
```

#### 7.4 Error Handling

**Common HTTP Errors:**
| Code | Name | Description | Resolution |
|------|------|-------------|------------|
| 400 | Bad Request | Invalid request data | Check request format |
| 401 | Unauthorized | Authentication failed | Check credentials |
| 403 | Forbidden | No permission | Check user role/permissions |
| 404 | Not Found | Resource not found | Check URL/ID |
| 422 | Unprocessable Entity | Validation failed | Check validation errors |
| 429 | Too Many Requests | Rate limit exceeded | Wait and retry |
| 500 | Internal Server Error | Server error | Check logs, report to DevOps |
| 502 | Bad Gateway | Gateway timeout | Check backend service |
| 503 | Service Unavailable | Maintenance mode | Wait, check /health |

**Validation Errors:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email field is required."
    ],
    "phone": [
      "The phone does not match the required format."
    ]
  }
}
```

**Database Errors:**
```
SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry
SQLSTATE[23503]: Foreign key constraint violation
SQLSTATE[42S02]: Base table or view not found
SQLSTATE[HY000]: Connection timeout
```

#### 7.5 Escalation Paths

**Technical Escalation:**
```
L1: Support Agent (Basic troubleshooting)
  ↓ If unresolved after 30 min
L2: Support Lead (Advanced troubleshooting)
  ↓ If unresolved after 1 hour
L3: NOC Engineer/DevOps (System-level investigation)
  ↓ If unresolved after 2 hours
L4: Engineering Manager (Architecture-level issues)
  ↓ If unresolved after 4 hours
L5: CTO (Strategic decisions)
```

**When to Escalate Immediately:**
- System-wide outage
- Data loss/corruption
- Security incident
- Payment processing failure
- Critical customer affected
- SLA breach imminent

**Escalation Information to Provide:**
1. Ticket ID/Reference
2. Customer information (ID, name, contact)
3. Issue description
4. Steps taken so far
5. Error messages/logs
6. Screenshots (if applicable)
7. Urgency/priority

---

## Training Materials

### Quick Reference Guides
1. **Customer Management QRG** - One-page reference for customer operations
2. **Billing & Payments QRG** - One-page reference for billing operations
3. **Network & RADIUS QRG** - One-page reference for network operations
4. **Ticketing QRG** - One-page reference for support operations
5. **Troubleshooting QRG** - Common issues and quick fixes

### Video Tutorials
| # | Title | Duration | Link |
|---|-------|----------|------|
| 1 | System Overview | 15 min | [Link] |
| 2 | Customer Creation | 10 min | [Link] |
| 3 | Billing Run | 12 min | [Link] |
| 4 | Payment Recording | 10 min | [Link] |
| 5 | RADIUS Troubleshooting | 15 min | [Link] |
| 6 | Ticket Management | 12 min | [Link] |

### Documentation
1. **Full User Manual** - Comprehensive guide for all features
2. **API Documentation** - For technical staff integrating systems
3. **Admin Guide** - For Filament admin panel users
4. **Customer Portal Guide** - For end-user support

---

## Assessment

### Written Assessment (30 minutes)
1. **Multiple Choice (20 questions)** - System knowledge, procedures
2. **Short Answer (10 questions)** - Troubleshooting scenarios
3. **Case Studies (3 scenarios)** - Real-world problem solving

### Practical Assessment (30 minutes)
1. **Customer Creation:** Create a customer with subscription
2. **Billing:** Run billing for test customer
3. **Payment:** Record manual payment
4. **Troubleshooting:** Diagnose and fix a connection issue
5. **Ticketing:** Create and resolve a support ticket

### Assessment Topics
| Module | Weight | Format |
|--------|--------|--------|
| System Overview | 10% | Multiple Choice |
| Customer Management | 15% | Practical |
| Billing & Invoicing | 15% | Practical |
| Payment Processing | 15% | Practical |
| Network & RADIUS | 15% | Multiple Choice |
| Ticketing & Support | 15% | Practical |
| Troubleshooting | 15% | Case Study |

### Passing Criteria
- **Overall Score:** Minimum 80%
- **Practical Score:** Minimum 85%
- **Case Studies:** All must demonstrate understanding

---

## Certification

### Certification Process
1. Complete all 7 modules
2. Pass written assessment (80%+)
3. Pass practical assessment (85%+)
4. Complete all hands-on exercises
5. Sign training completion form

### Certification Levels
| Level | Requirements | Privileges |
|-------|--------------|------------|
| Level 1 (Basic) | Complete training | Access to basic support functions |
| Level 2 (Advanced) | Level 1 + 3 months experience | Access to advanced functions, mentor new hires |
| Level 3 (Expert) | Level 2 + 1 year experience + specialization | Lead training, create documentation |

---

## Post-Training Support

### Mentoring
- Each new staff member assigned a mentor
- Mentor available for first 30 days
- Weekly check-ins

### Shadowing
- Shadow experienced staff for 1 week
- Gradual transition to independent work

### Refresher Training
- Quarterly refresher sessions
- New feature training as released
- Advanced training for career development

### Support Resources
- **Slack Channel:** #support-help
- **Email:** support-training@fiberloop.com
- **Documentation:** docs.fiberloop.com/support
- **Help Desk:** Internal ticketing system

---

## Training Schedule

### Week 1: Foundation Training (T-6 to T-5 Days)
| Date | Time | Module | Trainer | Location |
|------|------|--------|---------|----------|
| T-6 | 10:00-12:00 | Module 1: System Overview | Dev Lead | Training Room |
| T-6 | 14:00-16:00 | Module 2: Customer Management | Product Manager | Training Room |
| T-5 | 10:00-12:00 | Module 3: Billing & Invoicing | Billing Manager | Training Room |
| T-5 | 14:00-16:00 | Module 4: Payment Processing | Billing Manager | Training Room |

### Week 2: Technical Training (T-4 to T-3 Days)
| Date | Time | Module | Trainer | Location |
|------|------|--------|---------|----------|
| T-4 | 10:00-12:00 | Module 5: Network & RADIUS | NOC Lead | Training Room |
| T-4 | 14:00-16:00 | Module 6: Ticketing & Support | Support Lead | Training Room |
| T-3 | 10:00-12:00 | Module 7: Troubleshooting | DevOps Lead | Training Room |
| T-3 | 14:00-18:00 | Hands-on Practice | All Trainers | Lab |

### Assessment (T-2 Day)
| Date | Time | Activity | Location |
|------|------|----------|----------|
| T-2 | 10:00-11:00 | Written Assessment | Training Room |
| T-2 | 11:00-12:00 | Practical Assessment | Lab |
| T-2 | 14:00-15:00 | Review & Feedback | Training Room |

---

## Training Completion

### Completion Checklist
- [ ] Attended all training sessions
- [ ] Completed all hands-on exercises
- [ ] Passed written assessment
- [ ] Passed practical assessment
- [ ] Signed training completion form
- [ ] Received certification
- [ ] Assigned mentor
- [ ] Added to on-call rotation (if applicable)
- [ ] System access provisioned
- [ ] Welcome kit received

### Post-Training Survey
Please provide feedback on the training program:

1. **Overall Satisfaction (1-5):**
   - [ ] 1 (Poor)
   - [ ] 2 (Fair)
   - [ ] 3 (Good)
   - [ ] 4 (Very Good)
   - [ ] 5 (Excellent)

2. **Training Materials Quality (1-5):**

3. **Trainer Effectiveness (1-5):**

4. **Hands-on Exercises Usefulness (1-5):**

5. **Suggestions for Improvement:**

---

## Sign-off

**Training Status:** ✅ MATERIALS COMPLETE - Ready for delivery

- **Training Materials Prepared By:** Fiberloop Training Team
- **Date:** 2026-08-07
- **Approved For Production:** YES
- **Notes:** All training materials created. Ready to begin training sessions.

---

## Related Documents

- [Production Launch Plan](../PRODUCTION_LAUNCH_PLAN.md)
- [On-Call Drill Report](../alerting/ON_CALL_DRILL_REPORT.md)
- [Phase Verification Report](../PHASE_VERIFICATION.md)
