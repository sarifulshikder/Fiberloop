# User Acceptance Testing (UAT) - Fiberloop

## Overview

This document outlines the User Acceptance Testing (UAT) process for Fiberloop ISP Billing & Management Platform. UAT is the final verification step before production launch, ensuring that the system meets all business requirements and performs as expected in a staging environment that mirrors production.

## UAT Environment

### Prerequisites
- Staging environment with production-like configuration
- Database loaded with realistic (anonymized if using real data) data
- All Phase 0-16 features deployed and configured
- Test accounts for all user roles (super_admin, admin, noc_engineer, support_agent, billing_agent, reseller, field_technician, customer)
- All payment gateway sandbox credentials configured
- FreeRADIUS server running with test NAS devices
- Test MikroTik/OLT/ONU devices configured for monitoring

### Environment Setup

```bash
# Clone the repository
.git clone <repository-url> fiberloop-uat
cd fiberloop-uat

# Copy environment file
cp .env.example .env

# Configure staging environment
APP_ENV=staging
APP_URL=https://staging.fiberloop.com

# Database configuration (staging DB)
DB_CONNECTION=pgsql
DB_HOST=staging-db.fiberloop.com
DB_PORT=5432
DB_DATABASE=fiberloop_staging
DB_USERNAME=fiberloop_staging_user
DB_PASSWORD=secure_password_here

# Redis configuration
REDIS_HOST=staging-redis.fiberloop.com
REDIS_PASSWORD=redis_password_here

# Queue configuration
QUEUE_CONNECTION=redis

# Configure payment gateways with sandbox credentials
BKAsh_MODE=sandbox
BKAsh_API_KEY=sandbox_key
BKAsh_API_SECRET=sandbox_secret

NAGAD_MODE=sandbox
NAGAD_MERCHANT_ID=sandbox_merchant
NAGAD_API_KEY=sandbox_key

SSLCOMMERZ_MODE=sandbox
SSLCOMMERZ_STORE_ID=sandbox_store
SSLCOMMERZ_STORE_PASSWORD=sandbox_password

# FreeRADIUS configuration
RADIUS_HOST=staging-freeradius.fiberloop.com
RADIUS_PORT=1812
RADIUS_SECRET=staging_radius_secret
```

### Data Seeding

Run the following commands to seed the staging environment:

```bash
# Run all migrations
php artisan migrate:fresh --force

# Seed the database with test data
php artisan db:seed --force

# Generate realistic test data (1000+ customers, subscriptions, invoices)
php artisan db:seed --class=ProductionLikeDataSeeder --force

# Verify data
php artisan tinker --execute="\App\Models\Customer::count()"
# Should return > 1000
```

## Test Accounts

| Role | Email | Password | Purpose |
|------|-------|----------|---------|
| Super Admin | superadmin@fiberloop.com | secure123! | Full system access, configuration |
| Admin | admin@fiberloop.com | secure123! | Day-to-day administration |
| NOC Engineer | noc@fiberloop.com | secure123! | Network monitoring and device management |
| Support Agent | support@fiberloop.com | secure123! | Customer support, ticketing |
| Billing Agent | billing@fiberloop.com | secure123! | Billing, payments, invoicing |
| Reseller | reseller@fiberloop.com | secure123! | Reseller operations, commission tracking |
| Field Technician | fieldtech@fiberloop.com | secure123! | Field operations, installations |
| Customer | customer1@fiberloop.com | customer123! | Customer portal access |

## UAT Test Cases

### Phase 3: Customer / Subscriber Management

#### Test Case 1: Customer Creation
- **Actor**: Admin
- **Steps**:
  1. Login to admin panel at `/admin`
  2. Navigate to Customers > Create
  3. Fill in all customer details (personal info, service address, KYC info)
  4. Upload NID photos (front and back)
  5. Save the customer
- **Expected**: Customer is created successfully with all data saved
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 2: Customer Search and Filter
- **Actor**: Admin
- **Steps**:
  1. Navigate to Customers list
  2. Search by phone number
  3. Search by NID number
  4. Filter by package
  5. Filter by status
- **Expected**: Correct customers are returned for each search/filter
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 3: Customer Status Lifecycle
- **Actor**: Admin
- **Steps**:
  1. Create a new customer (status: pending)
  2. Activate the customer
  3. Suspend the customer
  4. Reactivate the customer
  5. Terminate the customer
- **Expected**: Status changes are reflected correctly, timeline shows all transitions
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Phase 4: Package & Pricing

#### Test Case 4: Package Creation
- **Actor**: Admin
- **Steps**:
  1. Navigate to Packages > Create
  2. Create a package with speed tiers, FUP, pricing
  3. Set package availability by zone
  4. Add promotional pricing
- **Expected**: Package is created and available for subscription
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 5: Package Change Request
- **Actor**: Customer
- **Steps**:
  1. Login to customer portal
  2. Navigate to Package Upgrade
  3. Select a different package
  4. Submit upgrade request
  5. Admin approves the request
- **Expected**: Package change is processed, prorated invoice generated if applicable
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Phase 5: Billing & Invoicing

#### Test Case 6: Billing Run
- **Actor**: System (scheduled job)
- **Steps**:
  1. Run `php artisan billing:run`
  2. Verify invoices are generated for all active subscriptions
  3. Check invoice numbering is sequential and gapless
  4. Verify proration for mid-cycle subscriptions
- **Expected**: All due invoices are generated correctly
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 7: Invoice PDF Generation
- **Actor**: Customer
- **Steps**:
  1. Login to customer portal
  2. Navigate to Invoices
  3. Click on an invoice
  4. Download the PDF
- **Expected**: PDF is generated with correct invoice details, totals, tax breakdown
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 8: Late Fee Application
- **Actor**: System
- **Steps**:
  1. Create an invoice with due date in the past (> grace period)
  2. Run late fee calculation
  3. Verify late fee is applied to the invoice
- **Expected**: Late fee is calculated and applied correctly
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 9: Auto-Suspend/Reactivate
- **Actor**: System
- **Steps**:
  1. Create an overdue invoice (> grace period)
  2. Run `php artisan subscriptions:suspend-overdue`
  3. Verify customer is suspended
  4. Make a payment for the invoice
  5. Verify customer is automatically reactivated
- **Expected**: Suspension and reactivation work automatically
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Phase 6: Payment Gateways

#### Test Case 10: bKash Payment
- **Actor**: Customer
- **Steps**:
  1. Login to customer portal
  2. Select an unpaid invoice
  3. Choose bKash as payment method
  4. Complete payment (use sandbox credentials)
- **Expected**: Payment is processed, invoice marked as paid, webhook received and verified
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 11: Nagad Payment
- **Actor**: Customer
- **Steps**:
  1. Login to customer portal
  2. Select an unpaid invoice
  3. Choose Nagad as payment method
  4. Complete payment (use sandbox credentials)
- **Expected**: Payment is processed, invoice marked as paid
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 12: SSLCommerz Payment
- **Actor**: Customer
- **Steps**:
  1. Login to customer portal
  2. Select an unpaid invoice
  3. Choose SSLCommerz as payment method
  4. Complete payment (use sandbox credentials)
- **Expected**: Payment is processed, invoice marked as paid
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 13: Manual Payment Entry
- **Actor**: Billing Agent
- **Steps**:
  1. Login to admin panel
  2. Navigate to Payments > Create
  3. Select a customer and invoice
  4. Enter payment amount (cash)
  5. Select field agent
  6. Save payment
- **Expected**: Payment is recorded, invoice status updated
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 14: Partial Payment
- **Actor**: Customer
- **Steps**:
  1. Select multiple unpaid invoices
  2. Make a payment that doesn't cover all invoices
- **Expected**: Payment is allocated to oldest invoices first, partial payments applied correctly
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 15: Refund Processing
- **Actor**: Billing Agent
- **Steps**:
  1. Navigate to a paid invoice
  2. Initiate refund
  3. Select refund reason
  4. Process refund
- **Expected**: Refund is processed, credit note generated, customer wallet credited
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Phase 7: FreeRADIUS Integration

#### Test Case 16: PPPoE Authentication
- **Actor**: System
- **Steps**:
  1. Create a customer with PPPoE connection type
  2. Provision RADIUS credentials
  3. Configure test NAS device
  4. Attempt PPPoE login with customer credentials
- **Expected**: Authentication succeeds, customer can access internet
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 17: Hotspot Authentication
- **Actor**: System
- **Steps**:
  1. Create a customer with Hotspot connection type
  2. Provision RADIUS credentials
  3. Attempt Hotspot login with customer credentials
- **Expected**: Authentication succeeds
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 18: Bandwidth Enforcement
- **Actor**: System
- **Steps**:
  1. Create a customer with FUP-enabled package
  2. Use data up to FUP threshold
  3. Verify bandwidth is throttled to configured speed
- **Expected**: Bandwidth is correctly throttled after FUP threshold
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 19: Auto-Disconnect on Suspension
- **Actor**: System
- **Steps**:
  1. Create an active customer with active session
  2. Suspend the customer subscription
- **Expected**: RADIUS session is terminated via CoA request
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Phase 8: Network Device Management

#### Test Case 20: Device Monitoring
- **Actor**: NOC Engineer
- **Steps**:
  1. Login to admin panel
  2. Navigate to Network > Devices
  3. View device status dashboard
  4. Check device metrics (ping, uptime, CPU, memory)
- **Expected**: Device metrics are displayed and updated every 5 minutes
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 21: Alert Creation
- **Actor**: System
- **Steps**:
  1. Simulate device outage (unplug device or stop SNMP)
  2. Wait for polling cycle (5 minutes)
  3. Check NOC dashboard for alerts
- **Expected**: Alert is created automatically for device outage
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 22: Incident Management
- **Actor**: NOC Engineer
- **Steps**:
  1. View alert in NOC dashboard
  2. Create incident from alert
  3. Assign incident to technician
  4. Update incident status
  5. Resolve incident
- **Expected**: Incident lifecycle is tracked correctly
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Phase 9: Reseller / Franchise Management

#### Test Case 23: Reseller Hierarchy
- **Actor**: Super Admin
- **Steps**:
  1. Create a master reseller
  2. Create a child reseller under the master
  3. Create a customer under the child reseller
  4. Verify data isolation (master can see child's customers, but not vice versa)
- **Expected**: Hierarchy works correctly, data is properly scoped
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 24: Commission Calculation
- **Actor**: System
- **Steps**:
  1. Create a reseller with commission rate
  2. Create a customer under the reseller
  3. Create a subscription and generate an invoice
  4. Make a payment for the invoice
- **Expected**: Commission is calculated and credited to reseller wallet
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 25: Commission Payout
- **Actor**: Admin
- **Steps**:
  1. View reseller commission ledger
  2. Verify commission amounts
  3. Process payout for reseller
- **Expected**: Payout is processed, reseller wallet is debited
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Phase 10: Ticketing & Field Operations

#### Test Case 26: Ticket Creation
- **Actor**: Customer
- **Steps**:
  1. Login to customer portal
  2. Navigate to Support > Create Ticket
  3. Select category, enter subject and description
  4. Submit ticket
- **Expected**: Ticket is created, customer receives notification
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 27: Ticket Assignment
- **Actor**: Support Agent
- **Steps**:
  1. Login to admin panel
  2. View tickets list
  3. Open a new ticket
  4. Assign to self
  5. Add internal note
  6. Reply to customer
- **Expected**: Ticket is assigned, customer receives reply notification
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 28: SLA Breach Alert
- **Actor**: System
- **Steps**:
  1. Create a ticket
  2. Don't respond within SLA timeframe
  3. Run SLA check job
- **Expected**: SLA breach alert is created and sent to support team
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 29: Field Job Dispatch
- **Actor**: Support Agent
- **Steps**:
  1. Create a ticket requiring field visit
  2. Dispatch field job
  3. Assign to field technician
- **Expected**: Field job is created, technician receives notification
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Phase 11: Notifications

#### Test Case 30: SMS Notification
- **Actor**: System
- **Steps**:
  1. Trigger an event that should send SMS (e.g., payment received)
  2. Check SMS log
- **Expected**: SMS is logged, sent to correct recipient
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 31: Email Notification
- **Actor**: System
- **Steps**:
  1. Trigger an event that should send email (e.g., invoice generated)
  2. Check email log
- **Expected**: Email is sent, logged correctly
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 32: Push Notification
- **Actor**: Customer
- **Steps**:
  1. Register FCM token via customer app
  2. Trigger an event that should send push notification
- **Expected**: Push notification is received on mobile device
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Phase 12: Dashboards & Reports

#### Test Case 33: Admin Dashboard
- **Actor**: Admin
- **Steps**:
  1. Login to admin panel
  2. View main dashboard
  3. Verify all widgets load correctly
- **Expected**: Dashboard shows real-time statistics, all widgets display data
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 34: Revenue Report
- **Actor**: Billing Agent
- **Steps**:
  1. Navigate to Reports > Revenue
  2. Select date range (last month)
  3. Generate report
  4. Export to CSV
- **Expected**: Report shows correct revenue data, CSV export works
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 35: Collection Report
- **Actor**: Billing Agent
- **Steps**:
  1. Navigate to Reports > Collection
  2. Select date range
  3. Generate report
- **Expected**: Report shows collection data, aged receivables
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Phase 13: AI & Analytics

#### Test Case 36: Churn Prediction
- **Actor**: System
- **Steps**:
  1. Run `php artisan ai:run-analysis`
  2. Wait for completion
  3. View AI Analytics dashboard
- **Expected**: Customers have churn scores, high-risk customers are flagged
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 37: Anomaly Detection
- **Actor**: System
- **Steps**:
  1. Run AI analysis
  2. Check customers for anomaly flags
- **Expected**: Anomalies (unusual usage patterns) are detected and flagged
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 38: Chatbot Interaction
- **Actor**: Customer
- **Steps**:
  1. Open live chat in customer portal
  2. Ask a common question (e.g., "How do I pay my bill?")
  3. Ask a question that requires escalation
- **Expected**: Chatbot responds to common questions, escalates complex ones
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Phase 14: Customer Portal & Mobile App

#### Test Case 39: Customer Login
- **Actor**: Customer
- **Steps**:
  1. Open customer portal
  2. Login with credentials
- **Expected**: Login succeeds, dashboard is displayed
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 40: View Invoice
- **Actor**: Customer
- **Steps**:
  1. Navigate to Invoices
  2. Select an invoice
  3. View invoice details
- **Expected**: Invoice details are displayed correctly
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 41: Make Payment
- **Actor**: Customer
- **Steps**:
  1. Select an unpaid invoice
  2. Choose payment method
  3. Complete payment
- **Expected**: Payment is processed, invoice status updated
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 42: Usage Viewing
- **Actor**: Customer
- **Steps**:
  1. Navigate to Usage
  2. View current usage
  3. View historical usage
- **Expected**: Usage data is displayed from RADIUS accounting
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 43: Ticket Management
- **Actor**: Customer
- **Steps**:
  1. Navigate to Tickets
  2. Create a new ticket
  3. View ticket status
  4. Reply to ticket
- **Expected**: Ticket management works correctly
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 44: Profile Management
- **Actor**: Customer
- **Steps**:
  1. Navigate to Profile
  2. Update personal information
  3. Update password
- **Expected**: Profile updates are saved
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Phase 15: Inventory & Assets

#### Test Case 45: Equipment Checkout
- **Actor**: Field Technician
- **Steps**:
  1. Login to admin panel
  2. Navigate to Inventory
  3. Check out equipment to self
  4. Assign to customer
- **Expected**: Equipment is assigned, stock levels updated
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 46: Equipment Return
- **Actor**: Field Technician
- **Steps**:
  1. Find assigned equipment
  2. Mark as returned
  3. Inspect and accept return
- **Expected**: Equipment is back in stock, available for assignment
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 47: Low Stock Alert
- **Actor**: System
- **Steps**:
  1. Set stock threshold for an item
  2. Consume stock to below threshold
  3. Run low stock check job
- **Expected**: Low stock alert is generated and sent
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Phase 16: Security & Data Hardening

#### Test Case 48: KYC Document Security
- **Actor**: Customer
- **Steps**:
  1. Upload KYC documents
  2. Attempt to access document URL directly (without authentication)
  3. Attempt to access another customer's KYC documents
- **Expected**: Documents are encrypted, access is restricted to authorized users only
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 49: Password Security
- **Actor**: Admin
- **Steps**:
  1. Check that passwords are hashed in database
  2. Verify password reset flow works
  3. Verify 2FA enforcement for admin roles
- **Expected**: Passwords are secure, 2FA is enforced
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

#### Test Case 50: Backup & Restore
- **Actor**: Super Admin
- **Steps**:
  1. Run `php artisan db:backup --encrypt`
  2. Verify backup file exists and is encrypted
  3. Run restore test (weekly job)
- **Expected**: Backup is created, encrypted, and can be restored
- **Actual**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

## Performance & Scalability Tests

### Test Case 51: Billing Run at Scale
- **Actor**: System
- **Steps**:
  1. Load 100,000 test subscriptions
  2. Run `php artisan billing:run`
  3. Measure execution time
  4. Verify all invoices are generated
- **Expected**: Billing run completes within acceptable time (< 2 hours for 100k)
- **Actual**: 
- **Time**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Test Case 52: RADIUS Auth at Scale
- **Actor**: System
- **Steps**:
  1. Create 100,000 test RADIUS users
  2. Simulate concurrent authentication requests (1000+ concurrent)
  3. Measure response times
- **Expected**: Authentication succeeds for all requests, average response time < 500ms
- **Actual**: 
- **Time**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

### Test Case 53: Database Query Performance
- **Actor**: System
- **Steps**:
  1. Load 500+ customers
  2. Run query: "All overdue invoices"
  3. Measure query time
  4. Run query: "Customers by zone"
  5. Measure query time
- **Expected**: Queries complete in < 1 second with proper indexing
- **Actual**: 
- **Time**: 
- **Status**: [ ] Pass [ ] Fail
- **Notes**:

## Sign-Off

### UAT Team
| Name | Role | Date | Signature |
|------|------|------|-----------|
| | Super Admin | | |
| | Admin | | |
| | NOC Engineer | | |
| | Support Agent | | |
| | Billing Agent | | |
| | Reseller | | |
| | Field Technician | | |
| | Customer | | |

### Approval
- [ ] All critical test cases passed
- [ ] All major bugs fixed
- [ ] Performance meets requirements
- [ ] Security review completed
- [ ] Training materials prepared
- [ ] Go-live date approved

**UAT Lead Sign-Off**: _______________________  Date: _________

**Product Owner Sign-Off**: ___________________  Date: _________

## Notes

1. UAT should be conducted in a staging environment that mirrors production as closely as possible.
2. Test data should be realistic and cover edge cases (empty data, boundary values, error conditions).
3. All test cases must be executed and documented.
4. Any failed test cases must be investigated and either fixed or accepted as known limitations.
5. Performance benchmarks should be established and verified.
6. Security testing should include penetration testing and code review.

## Known Issues

List any known issues that were identified during UAT but are accepted as-is for launch:

1. 
2. 
3. 

## Follow-Up Actions

List any actions that need to be taken after launch:

1. 
2. 
3. 
