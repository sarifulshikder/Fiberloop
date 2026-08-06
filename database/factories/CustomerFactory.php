<?php

namespace Database\Factories;

use App\Enums\ConnectionType;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'created_by' => \App\Models\User::factory(),
            'updated_by' => \App\Models\User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->phoneNumber(),
            'alternate_phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'nid_number' => fake()->unique()->numerify('#############'),
            'nid_front_photo' => null,
            'nid_back_photo' => null,
            'signature_photo' => null,
            'service_address' => fake()->address(),
            'service_latitude' => fake()->latitude(),
            'service_longitude' => fake()->longitude(),
            'billing_address' => fake()->address(),
            'connection_type' => fake()->randomElement(ConnectionType::cases()),
            'radius_username' => null,
            'radius_password' => null,
            'static_ip' => null,
            'mac_address' => null,
            'status' => CustomerStatus::ACTIVE,
            'activated_at' => now(),
            'suspended_at' => null,
            'terminated_at' => null,
            'suspension_reason' => null,
            'termination_reason' => null,
            'notes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerStatus::PENDING,
            'activated_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerStatus::SUSPENDED,
            'suspended_at' => now(),
            'suspension_reason' => 'Non-payment',
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CustomerStatus::TERMINATED,
            'terminated_at' => now(),
            'termination_reason' => 'Customer request',
        ]);
    }
}
