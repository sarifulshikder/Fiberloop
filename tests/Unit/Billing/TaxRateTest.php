<?php

namespace Tests\Unit\Billing;

use App\Models\Tenant;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive unit tests for TaxRate model business logic.
 * Tests cover tax calculation, rate resolution, and edge cases.
 */
class TaxRateTest extends TestCase
{
    use RefreshDatabase;

    // ==================== BASIC TAX CALCULATION TESTS ====================

    /**
     * Test tax calculation with standard rate.
     */
    public function test_tax_calculation_with_standard_rate(): void
    {
        // Create tax rate of 15%
        $taxRate = TaxRate::factory()->create([
            'rate' => 15, // 15%
        ]);

        // Calculate tax on 100000 poysha (1000 BDT)
        $taxAmount = $taxRate->calculateTax(100000);
        
        // 15% of 100000 = 15000
        $this->assertEquals(15000, $taxAmount);
    }

    /**
     * Test tax calculation with zero rate.
     */
    public function test_tax_calculation_with_zero_rate(): void
    {
        $taxRate = TaxRate::factory()->create([
            'rate' => 0,
        ]);

        $taxAmount = $taxRate->calculateTax(100000);
        
        $this->assertEquals(0, $taxAmount);
    }

    /**
     * Test tax calculation with high rate.
     */
    public function test_tax_calculation_with_high_rate(): void
    {
        $taxRate = TaxRate::factory()->create([
            'rate' => 25, // 25%
        ]);

        $taxAmount = $taxRate->calculateTax(100000);
        
        $this->assertEquals(25000, $taxAmount);
    }

    /**
     * Test tax calculation with small amount.
     */
    public function test_tax_calculation_with_small_amount(): void
    {
        $taxRate = TaxRate::factory()->create([
            'rate' => 15,
        ]);

        // 1 poysha = 0.01 BDT
        $taxAmount = $taxRate->calculateTax(1);
        
        // 15% of 1 = 0.15, rounded = 0
        $this->assertEquals(0, $taxAmount);
    }

    /**
     * Test tax calculation with large amount.
     */
    public function test_tax_calculation_with_large_amount(): void
    {
        $taxRate = TaxRate::factory()->create([
            'rate' => 15,
        ]);

        // 1,000,000 poysha = 10,000 BDT
        $taxAmount = $taxRate->calculateTax(1000000);
        
        // 15% of 1000000 = 150000
        $this->assertEquals(150000, $taxAmount);
    }

    /**
     * Test total calculation (base + tax).
     */
    public function test_total_calculation(): void
    {
        $taxRate = TaxRate::factory()->create([
            'rate' => 15,
        ]);

        $totalAmount = $taxRate->calculateTotal(100000);
        
        // 100000 + (15% of 100000) = 100000 + 15000 = 115000
        $this->assertEquals(115000, $totalAmount);
    }

    // ==================== ROUNDING TESTS ====================

    /**
     * Test tax calculation rounding down.
     */
    public function test_tax_calculation_rounding_down(): void
    {
        $taxRate = TaxRate::factory()->create([
            'rate' => 10, // 10%
        ]);

        // 10% of 101 = 10.1, should round to 10
        $taxAmount = $taxRate->calculateTax(101);
        
        $this->assertEquals(10, $taxAmount);
    }

    /**
     * Test tax calculation rounding up.
     */
    public function test_tax_calculation_rounding_up(): void
    {
        $taxRate = TaxRate::factory()->create([
            'rate' => 10, // 10%
        ]);

        // 10% of 105 = 10.5, should round to 11
        $taxAmount = $taxRate->calculateTax(105);
        
        $this->assertEquals(11, $taxAmount);
    }

    // ==================== RATE RESOLUTION TESTS ====================

    /**
     * Test getting current rate for tenant with active rate.
     */
    public function test_get_current_rate_for_tenant_with_active_rate(): void
    {
        $tenant = Tenant::factory()->create();

        TaxRate::factory()->create([
            'tenant_id' => $tenant->id,
            'rate' => 15,
            'is_active' => true,
            'is_default' => false,
        ]);

        $rate = TaxRate::getCurrentRateForTenant($tenant->id);
        
        $this->assertEquals(15, $rate);
    }

    /**
     * Test getting default rate for tenant with no specific rate.
     */
    public function test_get_default_rate_for_tenant_with_no_specific_rate(): void
    {
        $tenant = Tenant::factory()->create();

        // No tax rate for this tenant
        $rate = TaxRate::getCurrentRateForTenant($tenant->id);
        
        // Should return config default (15)
        $this->assertEquals(15, $rate);
    }

    /**
     * Test getting default rate for tenant.
     */
    public function test_get_default_rate_for_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        TaxRate::factory()->create([
            'tenant_id' => $tenant->id,
            'rate' => 20,
            'is_active' => false, // Not active
            'is_default' => true,
        ]);

        $rate = TaxRate::getDefaultRateForTenant($tenant->id);
        
        $this->assertEquals(20, $rate);
    }

    /**
     * Test getting global default rate.
     */
    public function test_get_global_default_rate(): void
    {
        TaxRate::factory()->create([
            'tenant_id' => null,
            'rate' => 10,
            'is_active' => true,
            'is_default' => true,
        ]);

        $rate = TaxRate::getGlobalDefaultRate();
        
        $this->assertEquals(10, $rate);
    }

    /**
     * Test rate effective date filtering.
     */
    public function test_rate_effective_date_filtering(): void
    {
        $tenant = Tenant::factory()->create();

        // Rate not yet effective
        TaxRate::factory()->create([
            'tenant_id' => $tenant->id,
            'rate' => 10,
            'is_active' => true,
            'effective_from' => now()->addDays(7)->toDateString(),
        ]);

        // Rate that is effective
        TaxRate::factory()->create([
            'tenant_id' => $tenant->id,
            'rate' => 15,
            'is_active' => true,
            'effective_from' => now()->subDays(7)->toDateString(),
        ]);

        $rate = TaxRate::getCurrentRateForTenant($tenant->id);
        
        // Should return the effective rate (15), not the future rate (10)
        $this->assertEquals(15, $rate);
    }

    /**
     * Test rate expiry date filtering.
     */
    public function test_rate_expiry_date_filtering(): void
    {
        $tenant = Tenant::factory()->create();

        // Rate that has expired
        TaxRate::factory()->create([
            'tenant_id' => $tenant->id,
            'rate' => 20,
            'is_active' => true,
            'effective_to' => now()->subDays(1)->toDateString(),
        ]);

        // Rate that is current
        TaxRate::factory()->create([
            'tenant_id' => $tenant->id,
            'rate' => 15,
            'is_active' => true,
            'effective_from' => now()->subDays(30)->toDateString(),
        ]);

        $rate = TaxRate::getCurrentRateForTenant($tenant->id);
        
        // Should return the current rate (15), not the expired rate (20)
        $this->assertEquals(15, $rate);
    }

    // ==================== EDGE CASE TESTS ====================

    /**
     * Test tax calculation with zero base amount.
     */
    public function test_tax_calculation_with_zero_base_amount(): void
    {
        $taxRate = TaxRate::factory()->create([
            'rate' => 15,
        ]);

        $taxAmount = $taxRate->calculateTax(0);
        
        $this->assertEquals(0, $taxAmount);
    }

    /**
     * Test total calculation with zero base amount.
     */
    public function test_total_calculation_with_zero_base_amount(): void
    {
        $taxRate = TaxRate::factory()->create([
            'rate' => 15,
        ]);

        $totalAmount = $taxRate->calculateTotal(0);
        
        $this->assertEquals(0, $totalAmount);
    }

    /**
     * Test tax calculation with maximum rate (100%).
     */
    public function test_tax_calculation_with_maximum_rate(): void
    {
        $taxRate = TaxRate::factory()->create([
            'rate' => 100, // 100%
        ]);

        $taxAmount = $taxRate->calculateTax(100000);
        
        // 100% of 100000 = 100000
        $this->assertEquals(100000, $taxAmount);
    }

    /**
     * Test that tax rate is always returned as integer.
     */
    public function test_tax_calculation_always_returns_integer(): void
    {
        $taxRate = TaxRate::factory()->create([
            'rate' => 15,
        ]);

        $taxAmount = $taxRate->calculateTax(100000);
        
        $this->assertIsInt($taxAmount);
    }

    /**
     * Test tax calculation with various base amounts.
     */
    public function test_tax_calculation_with_various_amounts(): void
    {
        $taxRate = TaxRate::factory()->create([
            'rate' => 15,
        ]);

        $testCases = [
            ['base' => 100, 'expected' => 15],
            ['base' => 200, 'expected' => 30],
            ['base' => 500, 'expected' => 75],
            ['base' => 1000, 'expected' => 150],
            ['base' => 10000, 'expected' => 1500],
            ['base' => 100000, 'expected' => 15000],
        ];

        foreach ($testCases as $case) {
            $taxAmount = $taxRate->calculateTax($case['base']);
            $this->assertEquals($case['expected'], $taxAmount,
                "Failed for base amount: {$case['base']}");
        }
    }

    // ==================== TAX RATE PRIORITY TESTS ====================

    /**
     * Test that active rates are prioritized over default rates.
     */
    public function test_active_rates_prioritized_over_default(): void
    {
        $tenant = Tenant::factory()->create();

        // Default rate
        TaxRate::factory()->create([
            'tenant_id' => $tenant->id,
            'rate' => 20,
            'is_active' => false,
            'is_default' => true,
        ]);

        // Active rate
        TaxRate::factory()->create([
            'tenant_id' => $tenant->id,
            'rate' => 15,
            'is_active' => true,
            'is_default' => false,
        ]);

        $currentRate = TaxRate::getCurrentRateForTenant($tenant->id);
        $defaultRate = TaxRate::getDefaultRateForTenant($tenant->id);
        
        $this->assertEquals(15, $currentRate);
        $this->assertEquals(20, $defaultRate);
    }

    /**
     * Test that most recent effective rate is returned when multiple are valid.
     */
    public function test_most_recent_effective_rate_returned(): void
    {
        $tenant = Tenant::factory()->create();

        // Older effective rate
        TaxRate::factory()->create([
            'tenant_id' => $tenant->id,
            'rate' => 10,
            'is_active' => true,
            'effective_from' => now()->subDays(30)->toDateString(),
        ]);

        // More recent effective rate
        TaxRate::factory()->create([
            'tenant_id' => $tenant->id,
            'rate' => 15,
            'is_active' => true,
            'effective_from' => now()->subDays(7)->toDateString(),
        ]);

        $rate = TaxRate::getCurrentRateForTenant($tenant->id);
        
        // Should return the most recent rate (15)
        $this->assertEquals(15, $rate);
    }

    // ==================== CONFIGURATION FALLBACK TESTS ====================

    /**
     * Test fallback to config when no rates exist.
     */
    public function test_fallback_to_config_when_no_rates_exist(): void
    {
        // No tax rates in database
        $rate = TaxRate::getCurrentRateForTenant(999);
        
        // Should return config default (15)
        $this->assertEquals(15, $rate);
    }

    /**
     * Test fallback to config for global default rate.
     */
    public function test_fallback_to_config_for_global_default_rate(): void
    {
        // No global default rate
        $rate = TaxRate::getGlobalDefaultRate();
        
        // Should return config default (15)
        $this->assertEquals(15, $rate);
    }
}
