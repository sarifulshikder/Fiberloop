<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup the test case - seed roles and permissions
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Test that billing_agent can view invoices but not network devices
     */
    public function test_billing_agent_can_view_invoices_but_not_network_devices(): void
    {
        $user = User::factory()->create();
        $user->assignRole('billing_agent');

        // Billing agent should have 'view invoices' permission
        $this->assertTrue($user->can('view invoices'));

        // Billing agent should NOT have 'view network devices' permission
        $this->assertFalse($user->can('view network devices'));
    }

    /**
     * Test that noc_engineer can view network devices but not payment gateways
     */
    public function test_noc_engineer_can_view_network_devices_but_not_payment_gateways(): void
    {
        $user = User::factory()->create();
        $user->assignRole('noc_engineer');

        // NOC engineer should have 'view network devices' permission
        $this->assertTrue($user->can('view network devices'));

        // NOC engineer should NOT have 'view payment gateways' permission
        $this->assertFalse($user->can('view payment gateways'));
    }

    /**
     * Test that super_admin has all permissions
     */
    public function test_super_admin_has_all_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        // Should have all permissions
        $this->assertTrue($user->can('view users'));
        $this->assertTrue($user->can('create users'));
        $this->assertTrue($user->can('delete users'));
        $this->assertTrue($user->can('view network devices'));
        $this->assertTrue($user->can('view payment gateways'));
        $this->assertTrue($user->can('view financial reports'));
    }

    /**
     * Test that support_agent can view tickets but not delete them
     */
    public function test_support_agent_can_view_but_not_delete_tickets(): void
    {
        $user = User::factory()->create();
        $user->assignRole('support_agent');

        // Support agent should have 'view tickets' permission
        $this->assertTrue($user->can('view tickets'));

        // Support agent should NOT have 'delete tickets' permission
        $this->assertFalse($user->can('delete tickets'));
    }

    /**
     * Test that customer has limited permissions
     */
    public function test_customer_has_limited_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        // Customer should have 'view customers' permission (for their own data)
        $this->assertTrue($user->can('view customers'));

        // Customer should NOT have 'view users' permission (admin function)
        $this->assertFalse($user->can('view users'));

        // Customer should NOT have 'create users' permission
        $this->assertFalse($user->can('create users'));
    }

    /**
     * Test that field_technician can edit onus but not payment gateways
     */
    public function test_field_technician_can_edit_onus_but_not_payment_gateways(): void
    {
        $user = User::factory()->create();
        $user->assignRole('field_technician');

        // Field technician should have 'edit onus' permission
        $this->assertTrue($user->can('edit onus'));

        // Field technician should NOT have 'configure payment gateways' permission
        $this->assertFalse($user->can('configure payment gateways'));
    }

    /**
     * Test that reseller can view their own earnings but not financial reports
     */
    public function test_reseller_can_view_earnings_but_not_financial_reports(): void
    {
        $user = User::factory()->create();
        $user->assignRole('reseller');

        // Reseller should have 'view reseller earnings' permission
        $this->assertTrue($user->can('view reseller earnings'));

        // Reseller should NOT have 'view financial reports' permission
        $this->assertFalse($user->can('view financial reports'));
    }

    /**
     * Test that permissions are properly scoped by role
     */
    public function test_permissions_are_properly_scoped_by_role(): void
    {
        $billingAgent = User::factory()->create();
        $billingAgent->assignRole('billing_agent');

        $nocEngineer = User::factory()->create();
        $nocEngineer->assignRole('noc_engineer');

        // Billing agent permissions
        $this->assertTrue($billingAgent->can('view invoices'));
        $this->assertTrue($billingAgent->can('create invoices'));
        $this->assertFalse($billingAgent->can('view olts'));

        // NOC engineer permissions
        $this->assertTrue($nocEngineer->can('view network devices'));
        $this->assertTrue($nocEngineer->can('view olts'));
        $this->assertFalse($nocEngineer->can('view invoices'));
    }

    /**
     * Test that admin role has most permissions
     */
    public function test_admin_role_has_most_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Admin should have most permissions
        $this->assertTrue($user->can('view users'));
        $this->assertTrue($user->can('edit users'));
        $this->assertTrue($user->can('view network devices'));
        $this->assertTrue($user->can('view payment gateways'));
        $this->assertTrue($user->can('view invoices'));
    }
}
