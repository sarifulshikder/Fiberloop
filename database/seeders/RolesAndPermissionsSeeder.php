<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the application's roles and permissions.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        // Create all permissions first
        $permissions = [
            // User management
            'view users',
            'create users',
            'edit users',
            'delete users',

            // Role & permission management
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            'manage permissions',

            // Customer management
            'view customers',
            'create customers',
            'edit customers',
            'delete customers',
            'view customer kyu',

            // Package management
            'view packages',
            'create packages',
            'edit packages',
            'delete packages',

            // Subscription management
            'view subscriptions',
            'create subscriptions',
            'edit subscriptions',
            'delete subscriptions',
            'suspend subscriptions',
            'reactivate subscriptions',

            // Invoice management
            'view invoices',
            'create invoices',
            'edit invoices',
            'delete invoices',
            'void invoices',
            'send invoices',

            // Payment management
            'view payments',
            'create payments',
            'edit payments',
            'delete payments',
            'refund payments',
            'view payment gateways',
            'configure payment gateways',

            // Reseller management
            'view resellers',
            'create resellers',
            'edit resellers',
            'delete resellers',
            'view reseller earnings',
            'manage reseller commissions',

            // Network device management
            'view network devices',
            'create network devices',
            'edit network devices',
            'delete network devices',
            'view olts',
            'create olts',
            'edit olts',
            'delete olts',
            'view olt ports',
            'create olt ports',
            'edit olt ports',
            'delete olt ports',
            'view onus',
            'create onus',
            'edit onus',
            'delete onus',
            'view radius customers',
            'create radius customers',
            'edit radius customers',
            'delete radius customers',

            // Ticket management
            'view tickets',
            'create tickets',
            'edit tickets',
            'delete tickets',
            'assign tickets',
            'close tickets',
            'reopen tickets',

            // Inventory management
            'view inventory',
            'create inventory',
            'edit inventory',
            'delete inventory',
            'assign inventory',
            'return inventory',

            // Financial reports
            'view financial reports',
            'view billing reports',
            'view collection reports',

            // System settings
            'view system settings',
            'edit system settings',
            'view audit logs',
            'view activity logs',

            // Notifications
            'send notifications',
            'view notification logs',

            // Dashboard access
            'view admin dashboard',
            'view noc dashboard',
            'view billing dashboard',
            'view support dashboard',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->command->info("Created " . count($permissions) . " permissions");

        // Create roles with appropriate permissions

        // 1. Super Admin - Full access to everything
        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web'
        ]);
        $superAdminRole->syncPermissions(Permission::all());
        $this->command->info("Created super_admin role with all permissions");

        // 2. Admin - Full access except super_admin functions
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web'
        ]);
        $adminRole->syncPermissions(Permission::all());
        $this->command->info("Created admin role");

        // 3. NOC Engineer - Network-focused, no billing/financial
        $nocEngineerRole = Role::firstOrCreate([
            'name' => 'noc_engineer',
            'guard_name' => 'web'
        ]);
        $nocEngineerPermissions = Permission::whereIn('name', [
            'view users',
            'view customers',
            'view network devices',
            'create network devices',
            'edit network devices',
            'delete network devices',
            'view olts',
            'create olts',
            'edit olts',
            'delete olts',
            'view olt ports',
            'create olt ports',
            'edit olt ports',
            'delete olt ports',
            'view onus',
            'create onus',
            'edit onus',
            'delete onus',
            'view radius customers',
            'create radius customers',
            'edit radius customers',
            'delete radius customers',
            'view tickets',
            'create tickets',
            'edit tickets',
            'assign tickets',
            'close tickets',
            'reopen tickets',
            'view inventory',
            'assign inventory',
            'return inventory',
            'view admin dashboard',
            'view noc dashboard',
        ])->pluck('id')->toArray();
        $nocEngineerRole->syncPermissions($nocEngineerPermissions);
        $this->command->info("Created noc_engineer role");

        // 4. Support Agent - Customer support, limited access
        $supportAgentRole = Role::firstOrCreate([
            'name' => 'support_agent',
            'guard_name' => 'web'
        ]);
        $supportAgentPermissions = Permission::whereIn('name', [
            'view users',
            'view customers',
            'view customer kyu',
            'view subscriptions',
            'view invoices',
            'view payments',
            'view tickets',
            'create tickets',
            'edit tickets',
            'assign tickets',
            'close tickets',
            'reopen tickets',
            'view inventory',
            'send notifications',
            'view notification logs',
            'view admin dashboard',
            'view support dashboard',
        ])->pluck('id')->toArray();
        $supportAgentRole->syncPermissions($supportAgentPermissions);
        $this->command->info("Created support_agent role");

        // 5. Billing Agent - Financial operations, no network access
        $billingAgentRole = Role::firstOrCreate([
            'name' => 'billing_agent',
            'guard_name' => 'web'
        ]);
        $billingAgentPermissions = Permission::whereIn('name', [
            'view users',
            'view customers',
            'create customers',
            'edit customers',
            'view customer kyu',
            'view packages',
            'view subscriptions',
            'create subscriptions',
            'edit subscriptions',
            'suspend subscriptions',
            'reactivate subscriptions',
            'view invoices',
            'create invoices',
            'edit invoices',
            'delete invoices',
            'void invoices',
            'send invoices',
            'view payments',
            'create payments',
            'edit payments',
            'delete payments',
            'refund payments',
            'view payment gateways',
            'configure payment gateways',
            'view resellers',
            'view reseller earnings',
            'view financial reports',
            'view billing reports',
            'view collection reports',
            'send notifications',
            'view notification logs',
            'view admin dashboard',
            'view billing dashboard',
        ])->pluck('id')->toArray();
        $billingAgentRole->syncPermissions($billingAgentPermissions);
        $this->command->info("Created billing_agent role");

        // 6. Reseller - Limited to their own data
        $resellerRole = Role::firstOrCreate([
            'name' => 'reseller',
            'guard_name' => 'web'
        ]);
        $resellerPermissions = Permission::whereIn('name', [
            'view customers',
            'create customers',
            'edit customers',
            'view customer kyu',
            'view packages',
            'view subscriptions',
            'create subscriptions',
            'view invoices',
            'view payments',
            'view reseller earnings',
            'view tickets',
            'create tickets',
            'send notifications',
        ])->pluck('id')->toArray();
        $resellerRole->syncPermissions($resellerPermissions);
        $this->command->info("Created reseller role");

        // 7. Field Technician - Limited to field operations
        $fieldTechnicianRole = Role::firstOrCreate([
            'name' => 'field_technician',
            'guard_name' => 'web'
        ]);
        $fieldTechnicianPermissions = Permission::whereIn('name', [
            'view customers',
            'edit customers',
            'view subscriptions',
            'edit subscriptions',
            'view network devices',
            'view olts',
            'view olt ports',
            'view onus',
            'edit onus',
            'view radius customers',
            'edit radius customers',
            'view inventory',
            'assign inventory',
            'return inventory',
            'view tickets',
            'create tickets',
            'edit tickets',
            'close tickets',
            'send notifications',
        ])->pluck('id')->toArray();
        $fieldTechnicianRole->syncPermissions($fieldTechnicianPermissions);
        $this->command->info("Created field_technician role");

        // 8. Customer - API-only role for customer portal
        $customerRole = Role::firstOrCreate([
            'name' => 'customer',
            'guard_name' => 'web'
        ]);
        $customerPermissions = Permission::whereIn('name', [
            'view customers',
            'edit customers',
            'view subscriptions',
            'view invoices',
            'view payments',
            'view tickets',
            'create tickets',
            'view notification logs',
        ])->pluck('id')->toArray();
        $customerRole->syncPermissions($customerPermissions);
        $this->command->info("Created customer role");

        $this->command->info("\nRoles and permissions seeding complete!");
    }
}
