<?php

namespace Database\Factories;

use App\Enums\DeviceVendor;
use App\Models\NetworkDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetworkDevice>
 */
class NetworkDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'created_by' => \App\Models\User::factory(),
            'updated_by' => \App\Models\User::factory(),
            'name' => fake()->words(2, true) . ' Router',
            'vendor' => fake()->randomElement(DeviceVendor::cases()),
            'model' => fake()->bothify('Model-????'),
            'serial_number' => fake()->unique()->bothify('SN-########'),
            'ip_address' => fake()->unique()->ipv4(),
            'hostname' => fake()->domainWord(),
            'port' => 22,
            'username' => 'admin',
            'password' => 'password',
            'snmp_community' => 'public',
            'snmp_version' => 'v2c',
            'location' => fake()->city(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'address' => fake()->address(),
            'is_active' => true,
            'last_checked_at' => now(),
            'is_reachable' => true,
            'capabilities' => json_encode(['nat', 'firewall', 'dhcp']),
            'configuration' => null,
            'notes' => null,
        ];
    }
}
