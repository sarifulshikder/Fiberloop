<?php

namespace Database\Factories;

use App\Enums\ResellerStatus;
use App\Models\Reseller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reseller>
 */
class ResellerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'parent_id' => null,
            'created_by' => 1,
            'updated_by' => 1,
            'name' => fake()->company(),
            'code' => fake()->unique()->bothify('RES-????'),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->unique()->phoneNumber(),
            'alternate_phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'status' => ResellerStatus::ACTIVE,
            'commission_rate' => fake()->numberBetween(500, 2000), // 5-20%
            'commission_amount' => 0,
            'wallet_balance' => fake()->numberBetween(0, 1000000),
            'total_earnings' => fake()->numberBetween(0, 10000000),
            'total_withdrawn' => fake()->numberBetween(0, 5000000),
            'contract_start_date' => fake()->dateTimeBetween('-2 years', '-1 year'),
            'contract_end_date' => fake()->dateTimeBetween('+1 year', '+5 years'),
            'contract_terms' => fake()->paragraph(),
            'activated_at' => now(),
            'suspended_at' => null,
            'terminated_at' => null,
            'suspension_reason' => null,
            'termination_reason' => null,
            'notes' => null,
        ];
    }

    public function withParent(Reseller $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ResellerStatus::SUSPENDED,
            'suspended_at' => now(),
            'suspension_reason' => 'Contract violation',
        ]);
    }
}