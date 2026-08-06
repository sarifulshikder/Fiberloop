<?php

namespace Database\Seeders;

use App\Enums\CustomerStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Database\Seeder;

class MassCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $packages = Package::query()->limit(10)->get();

        if ($packages->isEmpty()) {
            $this->command->error('No packages found.');
            return;
        }

        $this->command->info('Seeding 100,000 customers...');

        $batchSize = 1000;
        $total = 100000;

        for ($i = 0; $i < $total; $i += $batchSize) {
            $batch = min($batchSize, $total - $i);

            $customers = Customer::factory()
                ->count($batch)
                ->create([
                    'status' => fake()->randomElement([
                        CustomerStatus::PENDING,
                        CustomerStatus::ACTIVE,
                        CustomerStatus::ACTIVE,
                        CustomerStatus::ACTIVE,
                        CustomerStatus::SUSPENDED,
                        CustomerStatus::TERMINATED,
                    ]),
                ]);

            $subscriptions = [];
            foreach ($customers as $customer) {
                $package = $packages->random();
                $subscriptions[] = [
                    'uuid' => fake()->uuid(),
                    'customer_id' => $customer->id,
                    'package_id' => $package->id,
                    'monthly_price' => $package->price,
                    'final_price' => $package->price,
                    'status' => fake()->randomElement([
                        SubscriptionStatus::ACTIVE,
                        SubscriptionStatus::SUSPENDED,
                        SubscriptionStatus::EXPIRED,
                    ]),
                    'start_date' => now(),
                ];
            }

            Subscription::insert($subscriptions);
            $this->command->info("Batch: {$i}-" . ($i + $batch));
        }

        $this->command->info('Complete!');
    }
}
