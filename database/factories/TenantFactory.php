<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => fake()->unique()->numberBetween(1, 10000),
            'name' => fake()->company(),
            'uuid' => fake()->uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
