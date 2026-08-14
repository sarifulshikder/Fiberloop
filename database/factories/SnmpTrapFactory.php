<?php

namespace Database\Factories;

use App\Models\NetworkDevice;
use App\Models\SnmpTrap;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SnmpTrap>
 */
class SnmpTrapFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'network_device_id' => NetworkDevice::factory(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'host_ip' => fake()->ipv4(),
            'udp_port' => 162,
            'community_name' => 'public',
            'snmp_version' => fake()->randomElement(['v1', 'v2c', 'v3']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
