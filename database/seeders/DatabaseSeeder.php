<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\NetworkDevice;
use App\Models\NotificationLog;
use App\Models\Olt;
use App\Models\Onu;
use App\Models\Package;
use App\Models\Payment;
use App\Models\RadiusCustomer;
use App\Models\Reseller;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RolesAndPermissionsSeeder::class);

        // Create admin user
        $adminUser = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@fiberloop.com',
            'password' => Hash::make('password'),
            'phone' => '+8801700000000',
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        // Create billing agent
        $billingAgent = User::factory()->create([
            'name' => 'Billing Agent',
            'email' => 'billing@fiberloop.com',
            'password' => Hash::make('password'),
            'phone' => '+8801700000001',
            'is_super_admin' => false,
            'is_active' => true,
        ]);

        // Create NOC engineer
        $nocEngineer = User::factory()->create([
            'name' => 'NOC Engineer',
            'email' => 'noc@fiberloop.com',
            'password' => Hash::make('password'),
            'phone' => '+8801700000002',
            'is_super_admin' => false,
            'is_active' => true,
        ]);

        $adminUser->syncRoles(['super_admin', 'admin']);
        $billingAgent->syncRoles(['billing_agent']);
        $nocEngineer->syncRoles(['noc_engineer']);
        echo "Created users\n";

        echo "Database seeding complete!\n";
    }
}
