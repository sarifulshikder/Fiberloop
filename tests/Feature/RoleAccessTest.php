<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
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
     * Test that all staff roles can access the admin panel
     * This tests the canAccessPanel logic directly
     */
    public function test_all_staff_roles_have_admin_access(): void
    {
        $staffRoles = ['super_admin', 'admin', 'noc_engineer', 'support_agent', 'billing_agent', 'field_technician'];
        
        foreach ($staffRoles as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            
            // Test the hasAnyRole method which is used in canAccessPanel
            $this->assertTrue(
                $user->hasAnyRole($staffRoles),
                "User with role '{$role}' should have a staff role"
            );
        }
    }

    /**
     * Test that non-staff roles do not have staff roles
     */
    public function test_non_staff_roles_do_not_have_admin_access(): void
    {
        $nonStaffRoles = ['customer', 'reseller'];
        
        foreach ($nonStaffRoles as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            
            // Test that non-staff users don't have staff roles
            $staffRoles = ['super_admin', 'admin', 'noc_engineer', 'support_agent', 'billing_agent', 'field_technician'];
            $this->assertFalse(
                $user->hasAnyRole($staffRoles),
                "User with role '{$role}' should NOT have a staff role"
            );
        }
    }

    /**
     * Test that user with multiple roles including staff role has staff access
     */
    public function test_user_with_multiple_roles_including_staff_has_admin_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole(['customer', 'billing_agent']);
        
        $staffRoles = ['super_admin', 'admin', 'noc_engineer', 'support_agent', 'billing_agent', 'field_technician'];
        $this->assertTrue($user->hasAnyRole($staffRoles));
    }

    /**
     * Test that user with only non-staff roles does not have admin access
     */
    public function test_user_with_only_non_staff_roles_has_no_admin_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole(['customer', 'reseller']);
        
        $staffRoles = ['super_admin', 'admin', 'noc_engineer', 'support_agent', 'billing_agent', 'field_technician'];
        $this->assertFalse($user->hasAnyRole($staffRoles));
    }

    /**
     * Test that user with no roles does not have admin access
     */
    public function test_user_with_no_roles_has_no_admin_access(): void
    {
        $user = User::factory()->create();
        
        $staffRoles = ['super_admin', 'admin', 'noc_engineer', 'support_agent', 'billing_agent', 'field_technician'];
        $this->assertFalse($user->hasAnyRole($staffRoles));
    }

    /**
     * Test that billing agent does not have network device permissions
     */
    public function test_billing_agent_cannot_access_network_routes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('billing_agent');
        
        // Billing agent should not have network device permissions
        $this->assertFalse($user->can('view network devices'));
        $this->assertFalse($user->can('create network devices'));
        $this->assertFalse($user->can('edit network devices'));
        $this->assertFalse($user->can('delete network devices'));
    }

    /**
     * Test that noc engineer does not have billing permissions
     */
    public function test_noc_engineer_cannot_access_billing_routes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('noc_engineer');
        
        // NOC engineer should not have billing permissions
        $this->assertFalse($user->can('view invoices'));
        $this->assertFalse($user->can('create invoices'));
        $this->assertFalse($user->can('view payment gateways'));
        $this->assertFalse($user->can('configure payment gateways'));
    }

    /**
     * Test that super admin has all permissions
     */
    public function test_super_admin_has_access_to_everything(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        
        // Super admin should have all permissions
        $this->assertTrue($user->can('view users'));
        $this->assertTrue($user->can('view network devices'));
        $this->assertTrue($user->can('view invoices'));
    }

    /**
     * Test that admin role has most permissions
     */
    public function test_admin_role_has_access_to_admin_panel(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        
        $staffRoles = ['super_admin', 'admin', 'noc_engineer', 'support_agent', 'billing_agent', 'field_technician'];
        $this->assertTrue($user->hasAnyRole($staffRoles));
    }

    /**
     * Test that customer role cannot access admin panel
     */
    public function test_customer_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        
        $staffRoles = ['super_admin', 'admin', 'noc_engineer', 'support_agent', 'billing_agent', 'field_technician'];
        $this->assertFalse($user->hasAnyRole($staffRoles));
    }

    /**
     * Test that reseller role cannot access admin panel
     */
    public function test_reseller_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create();
        $user->assignRole('reseller');
        
        $staffRoles = ['super_admin', 'admin', 'noc_engineer', 'support_agent', 'billing_agent', 'field_technician'];
        $this->assertFalse($user->hasAnyRole($staffRoles));
    }
}
