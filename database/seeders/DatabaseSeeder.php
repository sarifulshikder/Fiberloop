<?php

namespace Database\Seeders;

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

        // Check if users already exist to avoid overwriting existing data
        $adminEmail = 'admin@fiberloop.com';
        $billingEmail = 'billing@fiberloop.com';
        $nocEmail = 'noc@fiberloop.com';

        $adminUser = User::where('email', $adminEmail)->first();
        $billingAgent = User::where('email', $billingEmail)->first();
        $nocEngineer = User::where('email', $nocEmail)->first();

        // Create admin user only if doesn't exist
        if (!$adminUser) {
            $adminUser = User::factory()->create([
                'name' => 'Super Admin',
                'email' => $adminEmail,
                'password' => Hash::make('password'),
                'phone' => '+8801700000000',
                'is_super_admin' => true,
                'is_active' => true,
            ]);
            echo "Created admin user\n";
        }

        // Create billing agent only if doesn't exist
        if (!$billingAgent) {
            $billingAgent = User::factory()->create([
                'name' => 'Billing Agent',
                'email' => $billingEmail,
                'password' => Hash::make('password'),
                'phone' => '+8801700000001',
                'is_super_admin' => false,
                'is_active' => true,
            ]);
            echo "Created billing agent\n";
        }

        // Create NOC engineer only if doesn't exist
        if (!$nocEngineer) {
            $nocEngineer = User::factory()->create([
                'name' => 'NOC Engineer',
                'email' => $nocEmail,
                'password' => Hash::make('password'),
                'phone' => '+8801700000002',
                'is_super_admin' => false,
                'is_active' => true,
            ]);
            echo "Created NOC engineer\n";
        }

        // Sync roles only if users were created or if roles don't exist
        if ($adminUser) {
            if (!$adminUser->hasRole('super_admin')) {
                $adminUser->syncRoles(['super_admin', 'admin']);
            }
        }
        if ($billingAgent) {
            if (!$billingAgent->hasRole('billing_agent')) {
                $billingAgent->syncRoles(['billing_agent']);
            }
        }
        if ($nocEngineer) {
            if (!$nocEngineer->hasRole('noc_engineer')) {
                $nocEngineer->syncRoles(['noc_engineer']);
            }
        }

        echo "Database seeding complete! Existing data preserved.\n";
    }
}
