# Fiberloop Database Schema

This document provides an overview of the Fiberloop ISP billing and management system database schema.

## Core Entities

### Authentication & Tenancy
- **tenants** - Multi-tenant support via stancl/tenancy (separate DB per tenant)
- **users** - Staff/admin users with auth credentials
- **roles** / **permissions** - RBAC via spatie/laravel-permission
- **activity_log** - Audit logging via spatie/laravel-activitylog

### Customer Management
- **customers** - Customer profiles, KYC data, addresses, connection details
- **resellers** - Reseller hierarchy with self-referencing parent_id
- **subscriptions** - Links customers to packages, tracks billing cycles

### Billing & Payments
- **packages** - Service packages with pricing, speeds, FUP settings
- **invoices** - Billing documents with status tracking
- **invoice_items** - Line items on invoices
- **payments** - Payment records with gateway references

### Network Management
- **network_devices** - Routers, switches, servers
- **olts** - GPON Optical Line Terminals
- **onus** - Optical Network Units (customer premise equipment)
- **radius_customers** - FreeRADIUS authentication mappings

### Operations
- **tickets** - Support ticketing system
- **inventory_items** - Equipment inventory tracking
- **notifications_log** - SMS/email/push notification audit

## Relationships

### Customer Domain
```
users --(created_by/updated_by)--> customers
customers --(tenant_id)--> tenants
customers --(1:n)--> subscriptions
customers --(1:n)--> invoices
customers --(1:n)--> payments
customers --(1:n)--> tickets
customers --(1:n)--> inventory_items
customers --(1:1)--> radius_customers
customers --(1:n)--> onus
```

### Billing Domain
```
packages --(1:n)--> subscriptions
subscriptions --(n:1)--> customers
subscriptions --(1:n)--> invoices
invoices --(1:n)--> invoice_items
invoices --(1:n)--> payments
payments --(n:1)--> invoices
```

### Network Domain
```
network_devices --(1:n)--> olts
olts --(1:n)--> onus
onus --(n:1)--> customers
onus --(n:1)--> subscriptions
network_devices --(1:n)--> subscriptions
olts --(1:n)--> subscriptions
```

### Reseller Domain
```
resellers --(self-referencing parent_id)--> resellers
resellers --(1:n)--> customers
resellers --(1:n)--> subscriptions
resellers --(1:n)--> invoices
resellers --(1:n)--> payments
resellers --(1:n)--> inventory_items
```

## Key Indexes

### Performance-Critical Indexes
- customers: [tenant_id, status], phone, nid_number, radius_username, email, uuid
- invoices: [tenant_id, customer_id], [tenant_id, status], [tenant_id, due_date], [due_date, status], invoice_number, uuid
- subscriptions: [tenant_id, customer_id], [tenant_id, next_billing_date], [next_billing_date]
- payments: [tenant_id, invoice_id], [tenant_id, customer_id], [invoice_id, status], [tenant_id, status], [gateway_reference, method], uuid

## Data Types

### Money Storage
All monetary amounts are stored as **unsignedBigInteger** representing the smallest currency unit (poysha = BDT × 100).

Examples:
- BDT 100.50 = 10050
- BDT 1,000.00 = 100000

Tables using this pattern:
- packages.price, installation_fee, security_deposit, tax_rate
- subscriptions.monthly_price, final_price, billing_cycle_discount, proration_amount
- invoices.subtotal, tax_amount, discount_amount, total, paid_amount, outstanding_amount
- payments.amount, fee_amount, net_amount
- inventory_items.purchase_price, selling_price

### Status Fields (Enums)
- CustomerStatus: PENDING, ACTIVE, SUSPENDED, TERMINATED
- InvoiceStatus: DRAFT, SENT, PAID, PARTIAL, OVERDUE, VOID
- PaymentStatus: PENDING, COMPLETED, FAILED, REFUNDED, DECLINED
- SubscriptionStatus: PENDING, ACTIVE, SUSPENDED, CANCELLED, EXPIRED, TERMINATED
- PackageBillingCycle: MONTHLY, QUARTERLY, BIANNUAL, ANNUAL, PREPAID
- BillingType: PREPAID, POSTPAID
- ConnectionType: PPPOE, HOTSPOT, STATIC
- TicketStatus: OPEN, IN_PROGRESS, ON_HOLD, RESOLVED, CLOSED, REOPENED
- TicketPriority: LOW, MEDIUM, HIGH, CRITICAL
- ResellerStatus: PENDING, ACTIVE, SUSPENDED, TERMINATED
- InventoryStatus: IN_STOCK, ASSIGNED, FAULTY, MAINTENANCE, RETIRED, LOST
- DeviceVendor: MIKROTIK, HUAWEI, CISCO, HPE, JUNIPER, OTHER

### Soft Deletes
The following tables use soft deletes:
- customers
- invoices
- payments
- subscriptions

### UUIDs
The following tables have UUID fields for public-facing identifiers:
- customers.uuid
- invoices.uuid
- payments.uuid
- subscriptions.uuid
- packages.uuid
- resellers.uuid
- network_devices.uuid
- olts.uuid
- onus.uuid
- radius_customers (no uuid, uses radius_username as identifier)
- tickets.uuid
- inventory_items.uuid
- notifications_log.uuid

## Database-Level Constraints

CHECK constraints ensure data integrity at the database level:

### Invoices Table
- subtotal >= 0
- tax_amount >= 0
- discount_amount >= 0
- total >= 0
- paid_amount >= 0
- outstanding_amount >= 0

### Payments Table
- amount >= 0
- fee_amount >= 0
- net_amount >= 0

### Packages Table
- price >= 0
- installation_fee >= 0
- security_deposit >= 0
- tax_rate >= 0

### Subscriptions Table
- monthly_price >= 0
- billing_cycle_discount >= 0
- final_price >= 0
- proration_amount >= 0

### Inventory Items Table
- purchase_price >= 0
- selling_price >= 0

## Multi-Tenancy

Implemented using **stancl/tenancy v3.10.0** with:
- Separate PostgreSQL database per tenant
- Redis tenancy bootstrapper enabled
- All business tables include nullable tenant_id column
- Shared tables: tenants, domains (from stancl/tenancy)
- Central tables: users, roles, permissions (shared across tenants)
