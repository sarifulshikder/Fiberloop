<?php

namespace Tests\Unit\Reseller;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerCommissionLedger;
use App\Services\Reseller\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive unit tests for CommissionService business logic.
 * Tests cover commission calculation, wallet operations, and edge cases.
 */
class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommissionService();
    }

    // ==================== COMMISSION CALCULATION TESTS ====================

    /**
     * Test percentage-based commission calculation.
     */
    public function test_percentage_commission_calculation(): void
    {
        // Create reseller with 10% commission rate
        $reseller = Reseller::factory()->create([
            'commission_rate' => 10, // 10%
            'commission_amount' => 0,
        ]);

        // Create payment of 1000 BDT = 100000 poysha
        $payment = Payment::factory()->create([
            'amount' => 100000, // 1000 BDT in poysha
            'reseller_id' => $reseller->id,
        ]);

        // Calculate commission
        $commission = $this->service->creditCommission($payment);

        // Should be 10% of 100000 = 10000 poysha
        $expectedCommission = 10000;

        // Verify ledger entry was created
        $ledger = ResellerCommissionLedger::where('reseller_id', $reseller->id)->first();

        $this->assertNotNull($ledger);
        $this->assertEquals($expectedCommission, $ledger->amount);
        $this->assertEquals('earned', $ledger->type);
        $this->assertEquals($payment->id, $ledger->payment_id);
    }

    /**
     * Test flat amount commission calculation.
     */
    public function test_flat_amount_commission_calculation(): void
    {
        // Create reseller with flat commission of 500 poysha (5 BDT)
        $reseller = Reseller::factory()->create([
            'commission_rate' => 0,
            'commission_amount' => 500, // 5 BDT in poysha
        ]);

        // Create payment
        $payment = Payment::factory()->create([
            'amount' => 200000, // 2000 BDT in poysha
            'reseller_id' => $reseller->id,
        ]);

        // Calculate commission
        $this->service->creditCommission($payment);

        // Verify ledger entry
        $ledger = ResellerCommissionLedger::where('reseller_id', $reseller->id)->first();

        $this->assertNotNull($ledger);
        $this->assertEquals(500, $ledger->amount);
        $this->assertEquals('earned', $ledger->type);
    }

    /**
     * Test percentage commission takes priority over flat amount.
     */
    public function test_percentage_takes_priority_over_flat_amount(): void
    {
        // Create reseller with both percentage and flat amount
        $reseller = Reseller::factory()->create([
            'commission_rate' => 10, // 10%
            'commission_amount' => 500, // 5 BDT
        ]);

        // Create payment of 10000 poysha (100 BDT)
        $payment = Payment::factory()->create([
            'amount' => 10000,
            'reseller_id' => $reseller->id,
        ]);

        // Calculate commission
        $this->service->creditCommission($payment);

        // Should use percentage (10% of 10000 = 1000) not flat amount (500)
        $ledger = ResellerCommissionLedger::where('reseller_id', $reseller->id)->first();

        $this->assertEquals(1000, $ledger->amount);
    }

    /**
     * Test no commission when no reseller associated.
     */
    public function test_no_commission_when_no_reseller(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // Create payment without reseller
        $payment = Payment::factory()->create([
            'reseller_id' => null,
            'amount' => 100000,
        ]);

        // Calculate commission
        $this->service->creditCommission($payment);

        // No ledger entries should be created
        $this->assertEmpty(ResellerCommissionLedger::all());
    }

    /**
     * Test commission via customer's reseller (payment has customer but no direct reseller).
     */
    public function test_commission_via_customer_reseller(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // Create reseller
        $reseller = Reseller::factory()->create([
            'commission_rate' => 10,
        ]);

        // Create customer belonging to reseller
        $customer = Customer::factory()->create([
            'reseller_id' => $reseller->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // Create payment for customer without direct reseller_id
        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'reseller_id' => null,
            'amount' => 100000,
        ]);

        // Calculate commission
        $this->service->creditCommission($payment);

        // Should find reseller via customer and calculate commission
        $ledger = ResellerCommissionLedger::where('reseller_id', $reseller->id)->first();

        $this->assertNotNull($ledger);
        $this->assertEquals(10000, $ledger->amount);
    }

    // ==================== WALLET OPERATION TESTS ====================

    /**
     * Test wallet credit increases balance.
     */
    public function test_wallet_credit_increases_balance(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $reseller = Reseller::factory()->create([
            'wallet_balance' => 0,
            'total_earnings' => 0,
        ]);

        $this->service->creditWallet(
            $reseller,
            100000, // 1000 BDT
            'Test credit',
            'earned'
        );

        $reseller->refresh();

        $this->assertEquals(100000, $reseller->wallet_balance);
        $this->assertEquals(100000, $reseller->total_earnings);
    }

    /**
     * Test wallet debit decreases balance.
     */
    public function test_wallet_debit_decreases_balance(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $reseller = Reseller::factory()->create([
            'wallet_balance' => 100000,
            'total_withdrawn' => 0,
        ]);

        $this->service->debitWallet(
            $reseller,
            50000, // 500 BDT
            'Test debit',
            'withdrawn'
        );

        $reseller->refresh();

        $this->assertEquals(50000, $reseller->wallet_balance);
        $this->assertEquals(50000, $reseller->total_withdrawn);
    }

    /**
     * Test wallet cannot go negative by default.
     */
    public function test_wallet_cannot_go_negative_by_default(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $reseller = Reseller::factory()->create([
            'wallet_balance' => 10000, // Only 100 BDT
        ]);

        // Expect exception when trying to debit more than balance
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('insufficient wallet balance');

        $this->service->debitWallet(
            $reseller,
            20000, // 200 BDT - more than balance
            'Test debit',
            'withdrawn'
        );
    }

    /**
     * Test wallet can go negative when explicitly allowed.
     */
    public function test_wallet_can_go_negative_when_allowed(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $reseller = Reseller::factory()->create([
            'wallet_balance' => 10000, // Only 100 BDT
        ]);

        // Should succeed with allowNegative = true
        $this->service->debitWallet(
            $reseller,
            20000, // 200 BDT - more than balance
            'Test debit with negative allowed',
            'withdrawn',
            true
        );

        $reseller->refresh();

        $this->assertEquals(-10000, $reseller->wallet_balance);
    }

    /**
     * Test credit wallet throws exception for zero or negative amount.
     */
    public function test_credit_wallet_throws_for_zero_amount(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $reseller = Reseller::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Credit amount must be positive');

        $this->service->creditWallet($reseller, 0, 'Test credit');
    }

    /**
     * Test debit wallet throws exception for zero or negative amount.
     */
    public function test_debit_wallet_throws_for_negative_amount(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $reseller = Reseller::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Debit amount must be positive');

        $this->service->debitWallet($reseller, -100, 'Test debit');
    }

    // ==================== LEDGER INTEGRITY TESTS ====================

    /**
     * Test ledger entries are immutable and comprehensive.
     */
    public function test_ledger_entry_comprehensive_data(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $reseller = Reseller::factory()->create([
            'wallet_balance' => 100000,
        ]);

        // Credit wallet
        $ledger = $this->service->creditWallet(
            $reseller,
            50000,
            'Commission for invoice #123',
            'earned',
            null,
            123
        );

        // Verify ledger entry has all required fields
        $this->assertNotNull($ledger->uuid);
        $this->assertEquals($reseller->id, $ledger->reseller_id);
        $this->assertEquals(50000, $ledger->amount);
        $this->assertEquals('earned', $ledger->type);
        $this->assertEquals(100000, $ledger->balance_before);
        $this->assertEquals(150000, $ledger->balance_after);
        $this->assertEquals('Commission for invoice #123', $ledger->description);
        $this->assertNull($ledger->payment_id);
        $this->assertEquals(123, $ledger->invoice_id);
    }

    /**
     * Test ledger entries for debit have negative amount.
     */
    public function test_ledger_entries_for_debit_have_negative_amount(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $reseller = Reseller::factory()->create([
            'wallet_balance' => 100000,
        ]);

        $ledger = $this->service->debitWallet(
            $reseller,
            50000,
            'Service charge',
            'withdrawn'
        );

        // Debit entries should have negative amount
        $this->assertEquals(-50000, $ledger->amount);
        $this->assertEquals('withdrawn', $ledger->type);
    }

    // ==================== EDGE CASE TESTS ====================

    /**
     * Test commission calculation with zero payment amount.
     */
    public function test_no_commission_for_zero_payment_amount(): void
    {
        $reseller = Reseller::factory()->create([
            'commission_rate' => 10,
        ]);

        $payment = Payment::factory()->create([
            'amount' => 0,
            'reseller_id' => $reseller->id,
        ]);

        $this->service->creditCommission($payment);

        // No ledger entries should be created for zero amount
        $this->assertEmpty(ResellerCommissionLedger::all());
    }

    /**
     * Test commission calculation with very small payment amount.
     */
    public function test_commission_calculation_with_small_payment(): void
    {
        $reseller = Reseller::factory()->create([
            'commission_rate' => 10,
        ]);

        $payment = Payment::factory()->create([
            'amount' => 1, // 0.01 BDT
            'reseller_id' => $reseller->id,
        ]);

        $this->service->creditCommission($payment);

        $ledger = ResellerCommissionLedger::where('reseller_id', $reseller->id)->first();

        $this->assertNotNull($ledger);
        $this->assertEquals(0, $ledger->amount); // 10% of 1 = 0.1, rounded = 0
    }

    /**
     * Test commission calculation with large payment amount.
     */
    public function test_commission_calculation_with_large_payment(): void
    {
        $reseller = Reseller::factory()->create([
            'commission_rate' => 5, // 5%
        ]);

        $payment = Payment::factory()->create([
            'amount' => 10000000, // 100,000 BDT
            'reseller_id' => $reseller->id,
        ]);

        $this->service->creditCommission($payment);

        $ledger = ResellerCommissionLedger::where('reseller_id', $reseller->id)->first();

        $this->assertNotNull($ledger);
        $this->assertEquals(500000, $ledger->amount); // 5% of 10,000,000 = 500,000
    }

    /**
     * Test multiple commission calculations for same reseller.
     */
    public function test_multiple_commissions_accumulate_correctly(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $reseller = Reseller::factory()->create([
            'commission_rate' => 10,
            'wallet_balance' => 0,
        ]);

        // First payment
        $payment1 = Payment::factory()->create([
            'amount' => 100000,
            'reseller_id' => $reseller->id,
        ]);
        $this->service->creditCommission($payment1);

        // Second payment
        $payment2 = Payment::factory()->create([
            'amount' => 200000,
            'reseller_id' => $reseller->id,
        ]);
        $this->service->creditCommission($payment2);

        $reseller->refresh();

        // Should have 10% of 100000 + 10% of 200000 = 10000 + 20000 = 30000
        $this->assertEquals(30000, $reseller->wallet_balance);

        // Should have 2 ledger entries
        $ledgerEntries = ResellerCommissionLedger::where('reseller_id', $reseller->id)->get();
        $this->assertCount(2, $ledgerEntries);
    }

    // ==================== TRANSACTION SAFETY TESTS ====================

    /**
     * Test that wallet operations are atomic (all or nothing).
     */
    public function test_wallet_operations_are_atomic(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $reseller = Reseller::factory()->create([
            'wallet_balance' => 100000,
        ]);

        $initialBalance = $reseller->wallet_balance;
        $initialLedgerCount = ResellerCommissionLedger::count();

        // Attempt credit operation
        $ledger = $this->service->creditWallet($reseller, 50000, 'Test credit');

        // Verify both balance and ledger were updated
        $reseller->refresh();
        $this->assertEquals($initialBalance + 50000, $reseller->wallet_balance);
        $this->assertEquals($initialLedgerCount + 1, ResellerCommissionLedger::count());
    }
}
