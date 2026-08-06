<?php

namespace Database\Factories;

use App\Enums\BillingType;
use App\Enums\PackageBillingCycle;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    public function definition(): array
    {
        $price = fake()->numberBetween(50000, 500000); // BDT 500-5000
        $installationFee = fake()->numberBetween(0, 200000);
        $securityDeposit = fake()->numberBetween(0, 500000);
        $taxRate = fake()->numberBetween(0, 2500); // 0-25%

        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'created_by' => null,
            'updated_by' => null,
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'code' => fake()->unique()->bothify('PKG-????'),
            'download_speed' => fake()->randomElement([10, 20, 30, 50, 100, 200, 300, 500]),
            'upload_speed' => fake()->randomElement([5, 10, 20, 50, 100, 200]),
            'fup_threshold' => fake()->numberBetween(100, 1000) * 1024, // In MB
            'fup_throttled_download' => fake()->randomElement([1, 2, 5, 10]),
            'fup_throttled_upload' => fake()->randomElement([1, 2, 5]),
            'price' => $price,
            'billing_cycle' => fake()->randomElement(PackageBillingCycle::cases()),
            'billing_type' => fake()->randomElement(BillingType::cases()),
            'installation_fee' => $installationFee,
            'security_deposit' => $securityDeposit,
            'tax_rate' => $taxRate,
            'is_active' => true,
            'is_popular' => fake()->boolean(),
            'sort_order' => fake()->numberBetween(0, 100),
            'features' => json_encode(['Unlimited', '24/7 Support', 'No FUP']),
        ];
    }
}
