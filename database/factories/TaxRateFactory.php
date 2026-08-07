<?php

namespace Database\Factories;

use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'tenant_id' => null,
            'code' => fake()->unique()->bothify('TAX-????'),
            'name' => fake()->words(2, true),
            'rate' => fake()->numberBetween(0, 3000), // 0-30%
            'description' => fake()->sentence(),
            'is_active' => true,
            'is_default' => false,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
