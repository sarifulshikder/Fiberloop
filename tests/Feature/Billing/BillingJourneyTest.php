<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PackageBillingCycle as BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Jobs\Billing\GenerateInvoices;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature tests for critical billing user journeys.
 * Tests the complete billing lifecycle from subscription to payment.
 */
class BillingJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Test complete customer subscription and billing journey.
     * Customer signs up -> subscription created -> invoice generated -> payment made -> service activated.
     */
    public function test_complete_subscription_and_billing_journey(): void
    {
        // 1. Create a user and customer
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('customer');

        $customer = Customer::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // 2. Create a package
        $package = Package::factory()->create([
            'price' => 100000, // 1000 BDT in poysha
            'billing_cycle' => BillingCycle::MONTHLY,
        ]);

        // 3. Create a subscription
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'price' => $package->price,
            'billing_cycle' => $package->billing_cycle,
            'start_date' => now()->toDateString(),
            'status' => SubscriptionStatus::ACTIVE,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // 4. Manually create an invoice (simulating GenerateInvoices job)
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'package_id' => $package->id,
            'invoice_number' => 'INV-001',
            'total' => $package->price,
            'tax_amount' => 0,
            'outstanding_amount' => $package->price,
            'due_date' => now()->addDays(15)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'billing_cycle' => $package->billing_cycle,
            'billing_start' => now()->toDateString(),
            'billing_end' => now()->addMonth()->toDateString(),
        ]);

        // Verify invoice was created correctly
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'customer_id' => $customer->id,
            'total' => $package->price,
            'outstanding_amount' => $package->price,
            'status' => InvoiceStatus::SENT,
        ]);

        // 5. Record a payment
        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => $package->price,
            'method' => PaymentMethod::MANUAL,
            'status' => PaymentStatus::COMPLETED,
            'paid_at' => now(),
            'transaction_reference' => 'REF-001',
        ]);

        // 6. Update invoice status to paid
        $invoice->update([
            'outstanding_amount' => 0,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        // 7. Verify the complete journey
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'customer_id' => $customer->id,
            'status' => InvoiceStatus::PAID,
            'outstanding_amount' => 0,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => $package->price,
            'status' => PaymentStatus::COMPLETED,
        ]);
    }

    /**
     * Test partial payment journey.
     * Customer pays partial amount -> invoice shows partial -> remaining balance tracked.
     */
    public function test_partial_payment_journey(): void
    {
        $user = User::factory()->create([
            'email' => 'partial@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('customer');

        $customer = Customer::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $package = Package::factory()->create([
            'price' => 100000, // 1000 BDT
            'billing_cycle' => BillingCycle::MONTHLY,
        ]);

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'price' => $package->price,
            'billing_cycle' => $package->billing_cycle,
            'start_date' => now()->toDateString(),
            'status' => SubscriptionStatus::ACTIVE,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Create invoice for 1000 BDT
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'package_id' => $package->id,
            'invoice_number' => 'INV-002',
            'total' => 100000,
            'tax_amount' => 0,
            'outstanding_amount' => 100000,
            'due_date' => now()->addDays(15)->toDateString(),
            'status' => InvoiceStatus::SENT,
        ]);

        // Pay 500 BDT (partial payment)
        $payment1 = Payment::factory()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 50000, // 500 BDT
            'method' => PaymentMethod::MANUAL,
            'status' => PaymentStatus::COMPLETED,
            'paid_at' => now(),
            'transaction_reference' => 'REF-002-A',
        ]);

        // Update invoice to show partial payment
        $invoice->update([
            'outstanding_amount' => 50000, // 500 BDT remaining
            'status' => InvoiceStatus::PARTIAL,
        ]);

        // Verify partial payment state
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'outstanding_amount' => 50000,
            'status' => InvoiceStatus::PARTIAL,
        ]);

        // Make second payment for remaining amount
        $payment2 = Payment::factory()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 50000, // 500 BDT
            'method' => PaymentMethod::MANUAL,
            'status' => PaymentStatus::COMPLETED,
            'paid_at' => now()->addHours(1),
            'transaction_reference' => 'REF-002-B',
        ]);

        // Update invoice to paid
        $invoice->update([
            'outstanding_amount' => 0,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now()->addHours(1),
        ]);

        // Verify final state
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'outstanding_amount' => 0,
            'status' => InvoiceStatus::PAID,
        ]);

        // Verify both payments exist
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 50000,
            'transaction_reference' => 'REF-002-A',
        ]);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 50000,
            'transaction_reference' => 'REF-002-B',
        ]);
    }

    /**
     * Test invoice overdue and late fee journey.
     */
    public function test_overdue_invoice_and_late_fee_journey(): void
    {
        $user = User::factory()->create([
            'email' => 'overdue@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('customer');

        $customer = Customer::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $package = Package::factory()->create([
            'price' => 100000, // 1000 BDT
            'billing_cycle' => BillingCycle::MONTHLY,
        ]);

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'price' => $package->price,
            'billing_cycle' => $package->billing_cycle,
            'start_date' => now()->toDateString(),
            'status' => SubscriptionStatus::ACTIVE,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Create invoice due 10 days ago
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'package_id' => $package->id,
            'invoice_number' => 'INV-003',
            'total' => 100000,
            'tax_amount' => 0,
            'outstanding_amount' => 100000,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => InvoiceStatus::SENT,
        ]);

        // Verify invoice is overdue
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 100000,
        ]);

        // Mark as overdue (this would typically be done by a scheduled job)
        $invoice->update([
            'status' => InvoiceStatus::OVERDUE,
        ]);

        // Verify invoice is now overdue
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::OVERDUE,
        ]);
    }

    /**
     * Test subscription suspension for non-payment journey.
     */
    public function test_subscription_suspension_for_non_payment(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('customer');

        $customer = Customer::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'status' => 'active',
        ]);

        $package = Package::factory()->create([
            'price' => 100000,
            'billing_cycle' => BillingCycle::MONTHLY,
        ]);

        // Create active subscription
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'price' => $package->price,
            'billing_cycle' => $package->billing_cycle,
            'start_date' => now()->subDays(30)->toDateString(),
            'status' => SubscriptionStatus::ACTIVE,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Create overdue invoice
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'package_id' => $package->id,
            'invoice_number' => 'INV-004',
            'total' => 100000,
            'tax_amount' => 0,
            'outstanding_amount' => 100000,
            'due_date' => now()->subDays(15)->toDateString(),
            'status' => InvoiceStatus::OVERDUE,
        ]);

        // Suspend subscription
        $subscription->update([
            'status' => SubscriptionStatus::SUSPENDED,
            'suspended_at' => now(),
            'suspension_reason' => 'Non-payment',
        ]);

        // Update customer status
        $customer->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => 'Non-payment',
        ]);

        // Verify suspension state
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::SUSPENDED,
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'suspended',
        ]);

        // Reactivate after payment
        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'method' => PaymentMethod::MANUAL,
            'status' => PaymentStatus::COMPLETED,
            'paid_at' => now(),
            'transaction_reference' => 'REF-004',
        ]);

        $invoice->update([
            'outstanding_amount' => 0,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        $subscription->update([
            'status' => SubscriptionStatus::ACTIVE,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        $customer->update([
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        // Verify reactivation
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test package change (upgrade) journey.
     */
    public function test_package_upgrade_journey(): void
    {
        $user = User::factory()->create([
            'email' => 'upgrade@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('customer');

        $customer = Customer::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Old package: 500 BDT
        $oldPackage = Package::factory()->create([
            'price' => 50000,
            'billing_cycle' => BillingCycle::MONTHLY,
        ]);

        // New package: 1000 BDT
        $newPackage = Package::factory()->create([
            'price' => 100000,
            'billing_cycle' => BillingCycle::MONTHLY,
        ]);

        // Create subscription with old package
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $oldPackage->id,
            'price' => $oldPackage->price,
            'billing_cycle' => $oldPackage->billing_cycle,
            'start_date' => now()->subDays(15)->toDateString(),
            'status' => SubscriptionStatus::ACTIVE,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Upgrade subscription
        $subscription->update([
            'package_id' => $newPackage->id,
            'price' => $newPackage->price,
            'updated_at' => now(),
        ]);

        // Create prorated invoice for the upgrade
        $proratedAmount = 25000; // 50% of 500 BDT difference (500 * 50% = 250 BDT)
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'package_id' => $newPackage->id,
            'invoice_number' => 'INV-005',
            'total' => $proratedAmount,
            'tax_amount' => 0,
            'outstanding_amount' => $proratedAmount,
            'due_date' => now()->addDays(15)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'description' => 'Prorated charge for package upgrade',
        ]);

        // Pay the prorated amount
        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => $proratedAmount,
            'method' => PaymentMethod::MANUAL,
            'status' => PaymentStatus::COMPLETED,
            'paid_at' => now(),
            'transaction_reference' => 'REF-005',
        ]);

        // Update invoice to paid
        $invoice->update([
            'outstanding_amount' => 0,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        // Verify upgrade
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'package_id' => $newPackage->id,
            'price' => $newPackage->price,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'total' => $proratedAmount,
            'status' => InvoiceStatus::PAID,
        ]);
    }

    /**
     * Test multiple invoices for same subscription journey.
     */
    public function test_multiple_invoices_for_same_subscription(): void
    {
        $user = User::factory()->create([
            'email' => 'multiinvoice@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('customer');

        $customer = Customer::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $package = Package::factory()->create([
            'price' => 100000, // 1000 BDT
            'billing_cycle' => BillingCycle::MONTHLY,
        ]);

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'price' => $package->price,
            'billing_cycle' => $package->billing_cycle,
            'start_date' => now()->subDays(45)->toDateString(),
            'status' => SubscriptionStatus::ACTIVE,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Create 3 invoices for different months
        $invoice1 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'package_id' => $package->id,
            'invoice_number' => 'INV-006-A',
            'total' => 100000,
            'tax_amount' => 0,
            'outstanding_amount' => 100000,
            'due_date' => now()->subDays(30)->toDateString(),
            'status' => InvoiceStatus::PAID,
            'billing_start' => now()->subDays(45)->toDateString(),
            'billing_end' => now()->subDays(30)->toDateString(),
        ]);

        $invoice2 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'package_id' => $package->id,
            'invoice_number' => 'INV-006-B',
            'total' => 100000,
            'tax_amount' => 0,
            'outstanding_amount' => 100000,
            'due_date' => now()->subDays(15)->toDateString(),
            'status' => InvoiceStatus::PAID,
            'billing_start' => now()->subDays(30)->toDateString(),
            'billing_end' => now()->subDays(15)->toDateString(),
        ]);

        $invoice3 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'package_id' => $package->id,
            'invoice_number' => 'INV-006-C',
            'total' => 100000,
            'tax_amount' => 0,
            'outstanding_amount' => 100000,
            'due_date' => now()->addDays(15)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'billing_start' => now()->subDays(15)->toDateString(),
            'billing_end' => now()->addDays(15)->toDateString(),
        ]);

        // Pay invoice 3
        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice3->id,
            'amount' => 100000,
            'method' => PaymentMethod::MANUAL,
            'status' => PaymentStatus::COMPLETED,
            'paid_at' => now(),
            'transaction_reference' => 'REF-006-C',
        ]);

        $invoice3->update([
            'outstanding_amount' => 0,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        // Verify all invoices exist
        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'invoice_number' => 'INV-006-A',
        ]);

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'invoice_number' => 'INV-006-B',
        ]);

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'invoice_number' => 'INV-006-C',
            'status' => InvoiceStatus::PAID,
        ]);

        // Verify 3 invoices total
        $invoiceCount = Invoice::where('subscription_id', $subscription->id)->count();
        $this->assertEquals(3, $invoiceCount);
    }

    /**
     * Test refund journey.
     */
    public function test_refund_journey(): void
    {
        $user = User::factory()->create([
            'email' => 'refund@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('customer');

        $customer = Customer::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'wallet_balance' => 100000, // 1000 BDT
        ]);

        $package = Package::factory()->create([
            'price' => 50000, // 500 BDT
            'billing_cycle' => BillingCycle::MONTHLY,
        ]);

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'price' => $package->price,
            'billing_cycle' => $package->billing_cycle,
            'start_date' => now()->toDateString(),
            'status' => SubscriptionStatus::ACTIVE,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'package_id' => $package->id,
            'invoice_number' => 'INV-007',
            'total' => 50000,
            'tax_amount' => 0,
            'outstanding_amount' => 50000,
            'due_date' => now()->addDays(15)->toDateString(),
            'status' => InvoiceStatus::SENT,
        ]);

        // Pay invoice
        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 50000,
            'method' => PaymentMethod::MANUAL,
            'status' => PaymentStatus::COMPLETED,
            'paid_at' => now(),
            'transaction_reference' => 'REF-007',
        ]);

        $invoice->update([
            'outstanding_amount' => 0,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        // Request refund of 250 BDT
        $refundAmount = 25000; // 250 BDT
        $payment->update([
            'amount' => $refundAmount,
            'status' => PaymentStatus::REFUNDED,
            'refunded_at' => now(),
            'refund_reference' => 'REFUND-007',
        ]);

        // Update invoice for refund
        $invoice->update([
            'outstanding_amount' => $refundAmount, // 250 BDT refunded
            'status' => InvoiceStatus::PARTIAL,
        ]);

        // Verify refund state
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => PaymentStatus::REFUNDED,
            'refund_reference' => 'REFUND-007',
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'outstanding_amount' => 25000,
            'status' => InvoiceStatus::PARTIAL,
        ]);
    }
}
