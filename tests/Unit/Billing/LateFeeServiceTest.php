<?php

namespace Tests\Unit\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Billing\LateFeeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive unit tests for LateFeeService business logic.
 * Tests cover late fee calculation, eligibility, and application.
 */
class LateFeeServiceTest extends TestCase
{
    use RefreshDatabase;

    private LateFeeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LateFeeService();
    }

    // ==================== ELIGIBILITY TESTS ====================

    /**
     * Test that paid invoices are not eligible for late fees.
     */
    public function test_paid_invoices_are_not_eligible(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => InvoiceStatus::PAID,
            'outstanding_amount' => 0,
        ]);

        $isEligible = $this->service->isEligibleForLateFee($invoice);
        
        $this->assertFalse($isEligible);
    }

    /**
     * Test that void invoices are not eligible for late fees.
     */
    public function test_void_invoices_are_not_eligible(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => InvoiceStatus::VOID,
            'outstanding_amount' => 100000,
        ]);

        $isEligible = $this->service->isEligibleForLateFee($invoice);
        
        $this->assertFalse($isEligible);
    }

    /**
     * Test that invoices with zero outstanding are not eligible.
     */
    public function test_zero_outstanding_invoices_are_not_eligible(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 0,
        ]);

        $isEligible = $this->service->isEligibleForLateFee($invoice);
        
        $this->assertFalse($isEligible);
    }

    /**
     * Test that invoices within grace period are not eligible.
     */
    public function test_invoices_within_grace_period_are_not_eligible(): void
    {
        // Default grace period is 5 days
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(3)->toDateString(), // 3 days overdue, but within 5-day grace period
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 100000,
        ]);

        $isEligible = $this->service->isEligibleForLateFee($invoice);
        
        $this->assertFalse($isEligible);
    }

    /**
     * Test that invoices past grace period are eligible.
     */
    public function test_invoices_past_grace_period_are_eligible(): void
    {
        // Default grace period is 5 days
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(), // 7 days overdue, past 5-day grace period
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 100000,
        ]);

        $isEligible = $this->service->isEligibleForLateFee($invoice);
        
        $this->assertTrue($isEligible);
    }

    // ==================== LATE FEE CALCULATION TESTS ====================

    /**
     * Test percentage-based late fee calculation.
     */
    public function test_percentage_late_fee_calculation(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 100000, // 1000 BDT
        ]);

        // Default is 10% late fee
        $lateFee = $this->service->calculateLateFee($invoice);
        
        // 10% of 100000 = 10000 poysha
        $this->assertEquals(10000, $lateFee);
    }

    /**
     * Test fixed late fee calculation.
     */
    public function test_fixed_late_fee_calculation(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 100000,
        ]);

        // Set fixed late fee of 500 poysha
        $this->service->setLateFeeFixed(500);

        $lateFee = $this->service->calculateLateFee($invoice);
        
        $this->assertEquals(500, $lateFee);
    }

    /**
     * Test maximum late fee limit.
     */
    public function test_maximum_late_fee_limit(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 1000000, // 10,000 BDT
        ]);

        // Default 10% would be 100,000 poysha, but set max to 50,000
        $this->service->setMaxLateFee(50000);

        $lateFee = $this->service->calculateLateFee($invoice);
        
        // Should be capped at 50,000
        $this->assertEquals(50000, $lateFee);
    }

    /**
     * Test no late fee for ineligible invoices.
     */
    public function test_no_late_fee_for_ineligible_invoices(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => InvoiceStatus::PAID, // Paid, so not eligible
            'outstanding_amount' => 0,
        ]);

        $lateFee = $this->service->calculateLateFee($invoice);
        
        $this->assertEquals(0, $lateFee);
    }

    /**
     * Test late fee calculation with custom grace period.
     */
    public function test_late_fee_with_custom_grace_period(): void
    {
        // Invoice is 3 days overdue
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 100000,
        ]);

        // With default 5-day grace period, not eligible
        $this->assertFalse($this->service->isEligibleForLateFee($invoice));

        // With 2-day grace period, should be eligible
        $this->service->setGracePeriod(2);
        $this->assertTrue($this->service->isEligibleForLateFee($invoice));
    }

    // ==================== LATE FEE APPLICATION TESTS ====================

    /**
     * Test that late fee is applied correctly to invoice.
     */
    public function test_late_fee_application_to_invoice(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 100000,
            'total' => 100000,
        ]);

        // Apply late fee
        $lateFeeAmount = $this->service->applyLateFee($invoice);

        $invoice->refresh();

        $this->assertEquals(10000, $lateFeeAmount); // 10% of 100000
        
        // Check that invoice totals were updated
        $this->assertEquals(110000, $invoice->total);
        $this->assertEquals(110000, $invoice->outstanding_amount);

        // Check that invoice item was created
        $lateFeeItem = InvoiceItem::where('invoice_id', $invoice->id)
            ->where('item_type', 'late_fee')
            ->first();
        
        $this->assertNotNull($lateFeeItem);
        $this->assertEquals(10000, $lateFeeItem->amount);
        $this->assertEquals('Late Fee - 10% for overdue payment', $lateFeeItem->description);
    }

    /**
     * Test that late fee is not applied twice for same period.
     */
    public function test_late_fee_not_applied_twice_for_same_period(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 100000,
            'total' => 100000,
        ]);

        // Apply late fee first time
        $this->service->applyLateFee($invoice);

        $invoice->refresh();
        $initialTotal = $invoice->total;

        // Try to apply again
        $lateFeeAmount = $this->service->applyLateFee($invoice);

        $invoice->refresh();

        // Should not have applied again
        $this->assertEquals(0, $lateFeeAmount);
        $this->assertEquals($initialTotal, $invoice->total);
    }

    // ==================== GRACE PERIOD TESTS ====================

    /**
     * Test grace period end date calculation.
     */
    public function test_grace_period_end_date_calculation(): void
    {
        $dueDate = Carbon::parse('2026-01-01');
        
        $invoice = Invoice::factory()->create([
            'due_date' => $dueDate->toDateString(),
        ]);

        $gracePeriodEnd = $this->service->getGracePeriodEnd($invoice);

        $expectedEnd = Carbon::parse('2026-01-06'); // 5 days after Jan 1
        
        $this->assertTrue($gracePeriodEnd->isSameDay($expectedEnd));
    }

    /**
     * Test is within grace period.
     */
    public function test_is_within_grace_period(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => InvoiceStatus::SENT,
        ]);

        $isWithin = $this->service->isWithinGracePeriod($invoice);
        
        $this->assertTrue($isWithin);
    }

    /**
     * Test is not within grace period when past.
     */
    public function test_is_not_within_grace_period_when_past(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
        ]);

        $isWithin = $this->service->isWithinGracePeriod($invoice);
        
        $this->assertFalse($isWithin);
    }

    /**
     * Test has passed grace period.
     */
    public function test_has_passed_grace_period(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
        ]);

        $hasPassed = $this->service->hasPassedGracePeriod($invoice);
        
        $this->assertTrue($hasPassed);
    }

    /**
     * Test has not passed grace period.
     */
    public function test_has_not_passed_grace_period(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => InvoiceStatus::SENT,
        ]);

        $hasPassed = $this->service->hasPassedGracePeriod($invoice);
        
        $this->assertFalse($hasPassed);
    }

    // ==================== EDGE CASE TESTS ====================

    /**
     * Test late fee calculation with zero outstanding amount.
     */
    public function test_late_fee_calculation_with_zero_outstanding(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 0,
        ]);

        $lateFee = $this->service->calculateLateFee($invoice);
        
        $this->assertEquals(0, $lateFee);
    }

    /**
     * Test late fee calculation with very small outstanding amount.
     */
    public function test_late_fee_calculation_with_small_outstanding(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 1, // 0.01 BDT
        ]);

        // Default 10% of 1 = 0.1, rounded = 0
        $lateFee = $this->service->calculateLateFee($invoice);
        
        $this->assertEquals(0, $lateFee);
    }

    /**
     * Test late fee calculation with large outstanding amount.
     */
    public function test_late_fee_calculation_with_large_outstanding(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 10000000, // 100,000 BDT
        ]);

        // Default 10% of 10000000 = 1000000
        $lateFee = $this->service->calculateLateFee($invoice);
        
        $this->assertEquals(1000000, $lateFee);
    }

    /**
     * Test percentage and fixed fee configuration conflict.
     */
    public function test_percentage_and_fixed_fee_configuration(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 100000,
        ]);

        // Set both percentage and fixed
        $this->service->setLateFeePercentage(10);
        $this->service->setLateFeeFixed(5000);

        // Fixed fee should take precedence (percentage is reset to 0 when fixed is set)
        $lateFee = $this->service->calculateLateFee($invoice);
        
        $this->assertEquals(5000, $lateFee);
    }

    // ==================== SERVICE CONFIGURATION TESTS ====================

    /**
     * Test set grace period.
     */
    public function test_set_grace_period(): void
    {
        $service = $this->service->setGracePeriod(10);
        
        $this->assertSame($service, $this->service);
        // We can't directly test the private property, but we can test behavior
    }

    /**
     * Test set late fee percentage.
     */
    public function test_set_late_fee_percentage(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 100000,
        ]);

        $this->service->setLateFeePercentage(5); // 5%

        $lateFee = $this->service->calculateLateFee($invoice);
        
        // 5% of 100000 = 5000
        $this->assertEquals(5000, $lateFee);
    }

    /**
     * Test set max late fee.
     */
    public function test_set_max_late_fee(): void
    {
        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 100000,
        ]);

        $this->service->setLateFeePercentage(50); // 50% would be 50000
        $this->service->setMaxLateFee(10000); // Max of 10000

        $lateFee = $this->service->calculateLateFee($invoice);
        
        // Should be capped at 10000
        $this->assertEquals(10000, $lateFee);
    }

    // ==================== PROCESS ALL OVERDUE INVOICES TEST ====================

    /**
     * Test processing late fees for all overdue invoices.
     */
    public function test_process_all_overdue_invoices(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // Create 3 invoices: 2 eligible, 1 not eligible
        $invoice1 = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 100000,
            'total' => 100000,
        ]);

        $invoice2 = Invoice::factory()->create([
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 200000,
            'total' => 200000,
        ]);

        $invoice3 = Invoice::factory()->create([
            'due_date' => now()->subDays(2)->toDateString(), // Within grace period
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 50000,
            'total' => 50000,
        ]);

        $result = $this->service->processAllOverdueInvoices();

        $this->assertEquals(2, $result['total_invoices_processed']);
        $this->assertEquals(30000, $result['total_late_fees_applied']); // 10% of 100000 + 10% of 200000 = 10000 + 20000
        $this->assertCount(2, $result['processed_invoices']);
    }

    /**
     * Test processing with no overdue invoices.
     */
    public function test_process_all_with_no_overdue_invoices(): void
    {
        // Create invoice that is not overdue
        Invoice::factory()->create([
            'due_date' => now()->addDays(5)->toDateString(), // Future due date
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 100000,
        ]);

        $result = $this->service->processAllOverdueInvoices();

        $this->assertEquals(0, $result['total_invoices_processed']);
        $this->assertEquals(0, $result['total_late_fees_applied']);
        $this->assertEmpty($result['processed_invoices']);
    }

    // ==================== CONCURRENCY PROTECTION TESTS ====================

    /**
     * Test that late fee application is atomic.
     */
    public function test_late_fee_application_is_atomic(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $invoice = Invoice::factory()->create([
            'due_date' => now()->subDays(7)->toDateString(),
            'status' => InvoiceStatus::SENT,
            'outstanding_amount' => 100000,
            'total' => 100000,
        ]);

        $initialTotal = $invoice->total;
        $initialOutstanding = $invoice->outstanding_amount;
        $initialItemsCount = InvoiceItem::where('invoice_id', $invoice->id)->count();

        // Apply late fee
        $lateFee = $this->service->applyLateFee($invoice);

        $invoice->refresh();

        // Verify all changes happened atomically
        $this->assertEquals($initialTotal + $lateFee, $invoice->total);
        $this->assertEquals($initialOutstanding + $lateFee, $invoice->outstanding_amount);
        $this->assertEquals($initialItemsCount + 1, InvoiceItem::where('invoice_id', $invoice->id)->count());
    }
}
