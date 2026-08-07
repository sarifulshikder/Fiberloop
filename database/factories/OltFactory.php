<?php

namespace Database\Factories;

use App\Models\NetworkDevice;
use App\Models\Olt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Olt>
 */
class OltFactory extends Factory
{
    public function definition(): array
    {
        $networkDevice = NetworkDevice::factory()->create();

        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'network_device_id' => $networkDevice->id,
            'created_by' => \App\Models\User::factory(),
            'updated_by' => \App\Models\User::factory(),
            'name' => fake()->words(2, true) . ' OLT',
            'chassis_id' => fake()->bothify('CH-????'),
            'firmware_version' => fake()->bothify('v?.?.?'),
            'hardware_version' => fake()->bothify('v?.?.?'),
            'uptime' => fake()->numberBetween(1, 365) . ' days',
            'total_pon_ports' => fake()->numberBetween(4, 16),
            'used_pon_ports' => fake()->numberBetween(0, 8),
            'max_onus_per_pon' => fake()->numberBetween(32, 128),
            'rack' => fake()->bothify('R?'),
            'slot' => fake()->numberBetween(1, 24),
            'location_notes' => fake()->sentence(),
            'is_active' => true,
            'last_sync_at' => now(),
            'configuration' => null,
            'notes' => null,
        ];
    }

    public function forNetworkDevice(NetworkDevice $device): static
    {
        return $this->state(fn (array $attributes) => [
            'network_device_id' => $device->id,
        ]);
    }
}
