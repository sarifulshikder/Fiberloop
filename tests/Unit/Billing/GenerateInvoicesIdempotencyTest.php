<?php

use App\Jobs\GenerateInvoices;
use App\Models\Invoice;
use App\Models\InvoiceNumberSequence;
use App\Models\Package;
use App\Models\Customer;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

beforeEach(function () {
    // Create test data
    $this->tenantId = 1;
    
    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenantId,
        'first_name' => 'Test',
        'last_name' => 'Customer',
        'phone' => '1234567890',
        'wallet_balance' => 0,
    ]);
    
    $this->package = Package::factory()->create([
        'name' => 'Test Package',
        'price' => 100000, // 1000 BDT
        'billing_cycle' => 'monthly',
        'speed_down' => 100,
        'speed_up' => 100,
    ]);
    
    $this->subscription = Subscription::factory()->create([
        'tenant_id' => $this->tenantId,
        'customer_id' => $this->customer->id,
        'package_id' => $this->package->id,
        'monthly_price' => 100000,
        'final_price' => 100000,
        'start_date' => '2026-01-01',
        'next_billing_date' => '2026-01-01',
        'status' => 'active',
    ]);
    
    // Clean up any existing invoices for this subscription
    Invoice::where('subscription_id', $this->subscription->id)->delete();
    
    // Ensure sequence exists
    InvoiceNumberSequence::firstOrCreate(
        ['tenant_id' => $this->tenantId],
        [
            'last_invoice_number' => 0,
            'last_credit_note_number' => 0,
            'last_refund_number' => 0,
        ]
    );
});

it('does not create duplicate invoices when run twice for same period', function () {
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    
    // Run first time
    $job1 = new GenerateInvoices($this->subscription->id, $cycleStart->copy(), $cycleEnd->copy());
    $job1->handle(app(InvoiceNumberGenerator::class), app(ProrationService::class));
    
    // Verify first invoice was created
    $invoicesAfterFirstRun = Invoice::where('subscription_id', $this->subscription->id)
        ->where('period_start', $cycleStart->toDateString())
        ->where('period_end', $cycleEnd->toDateString())
        ->count();
    
    expect($invoicesAfterFirstRun)->toBe(1);
    
    // Run second time for same period
    $job2 = new GenerateInvoices($this->subscription->id, $cycleStart->copy(), $cycleEnd->copy());
    $job2->handle(app(InvoiceNumberGenerator::class), app(ProrationService::class));
    
    // Verify no duplicate was created
    $invoicesAfterSecondRun = Invoice::where('subscription_id', $this->subscription->id)
        ->where('period_start', $cycleStart->toDateString())
        ->where('period_end', $cycleEnd->toDateString())
        ->count();
    
    expect($invoicesAfterSecondRun)->toBe(1);
});

it('creates separate invoices for different billing periods', function () {
    $cycleStart1 = Carbon::parse('2026-01-01');
    $cycleEnd1 = Carbon::parse('2026-01-31');
    
    $cycleStart2 = Carbon::parse('2026-02-01');
    $cycleEnd2 = Carbon::parse('2026-02-28');
    
    // Run for January
    $job1 = new GenerateInvoices($this->subscription->id, $cycleStart1->copy(), $cycleEnd1->copy());
    $job1->handle(app(InvoiceNumberGenerator::class), app(ProrationService::class));
    
    // Run for February
    $job2 = new GenerateInvoices($this->subscription->id, $cycleStart2->copy(), $cycleEnd2->copy());
    $job2->handle(app(InvoiceNumberGenerator::class), app(ProrationService::class));
    
    // Verify two separate invoices were created
    $januaryInvoices = Invoice::where('subscription_id', $this->subscription->id)
        ->where('period_start', $cycleStart1->toDateString())
        ->count();
    
    $februaryInvoices = Invoice::where('subscription_id', $this->subscription->id)
        ->where('period_start', $cycleStart2->toDateString())
        ->count();
    
    expect($januaryInvoices)->toBe(1);
    expect($februaryInvoices)->toBe(1);
    
    // Total should be 2
    $totalInvoices = Invoice::where('subscription_id', $this->subscription->id)->count();
    expect($totalInvoices)->toBe(2);
});

it('skips inactive subscriptions', function () {
    // Deactivate subscription
    $this->subscription->update(['status' => 'suspended']);
    
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    
    $job = new GenerateInvoices($this->subscription->id, $cycleStart, $cycleEnd);
    $job->handle(app(InvoiceNumberGenerator::class), app(ProrationService::class));
    
    // Verify no invoice was created
    $invoices = Invoice::where('subscription_id', $this->subscription->id)->count();
    expect($invoices)->toBe(0);
});

it('handles missing subscription gracefully', function () {
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    
    // Non-existent subscription ID
    $nonExistentId = 99999;
    
    $job = new GenerateInvoices($nonExistentId, $cycleStart, $cycleEnd);
    $job->handle(app(InvoiceNumberGenerator::class), app(ProrationService::class));
    
    // Should not throw exception, just log warning
    // Verify no invoice was created
    $invoices = Invoice::where('subscription_id', $nonExistentId)->count();
    expect($invoices)->toBe(0);
});

it('generates invoice with correct amounts', function () {
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    
    $job = new GenerateInvoices($this->subscription->id, $cycleStart, $cycleEnd);
    $job->handle(app(InvoiceNumberGenerator::class), app(ProrationService::class));
    
    $invoice = Invoice::where('subscription_id', $this->subscription->id)->first();
    
    expect($invoice)->not->toBeNull();
    expect($invoice->subtotal)->toBe(100000); // Package price
    expect($invoice->total)->toBeGreaterThan($invoice->subtotal); // Should include tax
    expect($invoice->outstanding_amount)->toBe($invoice->total);
    expect($invoice->paid_amount)->toBe(0);
});

it('updates subscription next billing date', function () {
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    
    $job = new GenerateInvoices($this->subscription->id, $cycleStart, $cycleEnd);
    $job->handle(app(InvoiceNumberGenerator::class), app(ProrationService::class));
    
    // Refresh subscription
    $this->subscription->refresh();
    
    // Next billing date should be set to start of next month
    expect($this->subscription->next_billing_date)->toBe('2026-02-01');
});

it('creates invoice items along with invoice', function () {
    $cycleStart = Carbon::parse('2026-01-01');
    $cycleEnd = Carbon::parse('2026-01-31');
    
    $job = new GenerateInvoices($this->subscription->id, $cycleStart, $cycleEnd);
    $job->handle(app(InvoiceNumberGenerator::class), app(ProrationService::class));
    
    $invoice = Invoice::where('subscription_id', $this->subscription->id)->first();
    
    expect($invoice->items)->not->toBeEmpty();
    expect($invoice->items->count())->toBe(1);
    
    $item = $invoice->items->first();
    expect($item->description)->toContain($this->package->name);
    expect($item->amount)->toBe(100000);
});
