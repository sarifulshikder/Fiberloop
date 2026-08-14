<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Safe database seeding command that preserves existing data.
 * This command will only create missing users and roles, without
 * deleting or modifying any existing data.
 */
class SafeSeedDatabase extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:seed-safe
        {--force : Force seeding even if data exists}
        {--users-only : Only seed users and roles, skip other seeders}';

    /**
     * The console command description.
     */
    protected $description = 'Safely seed the database without deleting existing data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting safe database seeding...');
        $this->info('This will NOT delete any existing data.');
        $this->newLine();

        // Check if there are existing users
        $existingUsers = User::count();

        if ($existingUsers > 0 && !$this->option('force')) {
            $this->warn("Found {$existingUsers} existing users in the database.");
            $this->warn('Safe seeding will only create missing default users and roles.');
            $this->newLine();
        }

        // Only run the main DatabaseSeeder which now checks for existing users
        $this->info('Seeding roles and permissions...');
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
            '--force' => true,
        ]);
        $this->info('Roles and permissions seeded.');

        $this->info('Seeding users (will skip if already exist)...');
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\DatabaseSeeder',
            '--force' => true,
        ]);
        $this->info('Users seeded (existing users preserved).');

        if (!$this->option('users-only')) {
            $this->info('\nNote: Other seeders (like MassCustomerSeeder) are NOT run to preserve your data.');
            $this->info('If you need test data, use: php artisan db:seed --class=Database\\Seeders\\MassCustomerSeeder');
        }

        $this->newLine();
        $this->info('✅ Safe seeding complete! Your existing data is preserved.');

        return 0;
    }
}
