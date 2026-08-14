<?php

namespace Database\Factories;

use App\Models\NetworkDevice;
use App\Models\SnmpCommunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SnmpCommunity>
 */
class SnmpCommunityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'network_device_id' => NetworkDevice::factory(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'community_name' => 'public',
            'access_right' => fake()->randomElement(['read-only', 'read-write']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
