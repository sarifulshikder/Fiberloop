<?php

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\User;
use App\Services\Reseller\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Phase 9: Reseller Management', function () {
    beforeEach(function () {
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'reseller', 'guard_name' => 'web']);

        $this->tenant = \App\Models\Tenant::create(['name' => 'Test Tenant']);
        tenancy()->initialize($this->tenant);

        $this->admin = User::factory()->create(['id' => 1, 'tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('super_admin');
        \Illuminate\Support\Facades\DB::statement("SELECT setval('users_id_seq', (SELECT MAX(id) FROM users))");

        $this->commissionService = app(CommissionService::class);
    });

    it('calculates percentage commission correctly on payment', function () {
        $reseller = Reseller::factory()->create([
            'tenant_id' => $this->tenant->id,
            'commission_rate' => 10, // 10%
            'commission_amount' => 0,
            'wallet_balance' => 0,
            'total_earnings' => 0,
        ]);

        // Create a customer linked to this reseller
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reseller_id' => $reseller->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        // Simulate a payment of ৳1000 (100000 poysha)
        $payment = Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reseller_id' => $reseller->id,
            'customer_id' => $customer->id,
            'amount' => 100000, // ৳1000 in poysha
        ]);

        $this->commissionService->creditCommission($payment);

        // 10% of 100000 = 10000 poysha = ৳100
        $reseller->refresh();
        expect($reseller->wallet_balance)->toBe(10000);
        expect($reseller->total_earnings)->toBe(10000);

        // Ledger entry must exist
        $this->assertDatabaseHas('reseller_commission_ledger', [
            'reseller_id' => $reseller->id,
            'type' => 'earned',
            'amount' => 10000,
            'balance_before' => 0,
            'balance_after' => 10000,
        ]);
    });

    it('calculates flat commission correctly on payment', function () {
        $reseller = Reseller::factory()->create([
            'tenant_id' => $this->tenant->id,
            'commission_rate' => 0,
            'commission_amount' => 5000, // flat ৳50 (5000 poysha)
            'wallet_balance' => 0,
            'total_earnings' => 0,
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reseller_id' => $reseller->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $payment = Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reseller_id' => $reseller->id,
            'customer_id' => $customer->id,
            'amount' => 200000,
        ]);

        $this->commissionService->creditCommission($payment);

        $reseller->refresh();
        expect($reseller->wallet_balance)->toBe(5000); // flat, not % of 200000
        expect($reseller->total_earnings)->toBe(5000);
    });

    it('wallet balance never goes negative without explicit override', function () {
        $reseller = Reseller::factory()->create([
            'tenant_id' => $this->tenant->id,
            'wallet_balance' => 1000, // only ৳10
        ]);

        // Attempting to debit ৳50 (5000 poysha) should throw
        expect(fn () => $this->commissionService->debitWallet(
            $reseller,
            5000,
            'Test debit that exceeds balance',
        ))->toThrow(\RuntimeException::class);

        // Balance must be unchanged
        $reseller->refresh();
        expect($reseller->wallet_balance)->toBe(1000);
    });

    it('allows negative wallet with explicit override', function () {
        $reseller = Reseller::factory()->create([
            'tenant_id' => $this->tenant->id,
            'wallet_balance' => 0,
        ]);

        $this->commissionService->debitWallet(
            $reseller,
            3000,
            'Emergency debit with override',
            'adjusted',
            allowNegative: true,
        );

        $reseller->refresh();
        expect($reseller->wallet_balance)->toBe(-3000);
    });

    it('reseller scope filters customers correctly', function () {
        $resellerA = Reseller::factory()->create(['tenant_id' => $this->tenant->id]);
        $resellerB = Reseller::factory()->create(['tenant_id' => $this->tenant->id]);

        $customerA = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reseller_id' => $resellerA->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $customerB = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'reseller_id' => $resellerB->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        // Create a user with reseller role linked to resellerA by email
        $resellerUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => $resellerA->email ?? 'resellerA@example.com',
        ]);
        $resellerA->update(['email' => $resellerUser->email]);
        $resellerUser->assignRole('reseller');

        // Act as the reseller user — the scope should apply
        $this->actingAs($resellerUser);

        $visibleCustomers = Customer::all();

        // Should only see resellerA's customers
        expect($visibleCustomers->pluck('id'))->toContain($customerA->id);
        expect($visibleCustomers->pluck('id'))->not->toContain($customerB->id);
    });

    it('demonstrates 2-level reseller hierarchy', function () {
        $parent = Reseller::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => null,
        ]);

        $child = Reseller::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $parent->id,
        ]);

        $grandchild = Reseller::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $child->id,
        ]);

        // Verify hierarchy relations
        expect($child->parent->id)->toBe($parent->id);
        expect($grandchild->parent->id)->toBe($child->id);
        expect($grandchild->parent->parent->id)->toBe($parent->id);

        // Parent can see both direct children and grandchildren via recursive loading
        expect($parent->children()->count())->toBe(1);
        expect($child->children()->count())->toBe(1);
    });
});
