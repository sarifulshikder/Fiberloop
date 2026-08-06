<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Olt;
use App\Models\Onu;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Onu>
 */
class OnuFactory extends Factory
{
    public function definition(): array
    {
        $olt = Olt::factory()->create();
        $customer = Customer::factory()->create();

        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'olt_id' => $olt->id,
            'customer_id' => $customer->id,
            'subscription_id' => null,
            'created_by' => 1,
            'updated_by' => 1,
            'serial_number' => fake()->unique()->bothify('ONU-########'),
            'mac_address' => fake()->unique()->macAddress(),
            'ONU_id' => fake()->numberBetween(1, 255),
            'pon_port' => fake()->numberBetween(1, 4),
            'pon_port_name' => fake()->bothify('PON-?/?'),
            'registration_id' => fake()->unique()->bothify('REG-????'),
            'registered_at' => now(),
            'is_registered' => true,
            'optical_signal_db' => fake()->numberBetween(-2500, -1500) / 100, // -25.00 to -15.00
            'tx_power_db' => fake()->numberBetween(0, 1000) / 100,
            'rx_power_db' => fake()->numberBetween(0, 1000) / 100,
            'vendor_id' => fake()->bothify('VEND-????'),
            'firmware_version' => fake()->bothify('v?.?.?'),
            'hardware_version' => fake()->bothify('v?.?.?'),
            'ONU_type' => fake()->bothify('Type-????'),
            'is_active' => true,
            'operational_state' => 'online',
            'last_signal_check_at' => now(),
            'distance_meters' => fake()->numberBetween(100, 5000),
            'configuration' => null,
            'notes' => null,
        ];
    }

    public function forOlt(Olt $olt): static
    {
        return $this->state(fn (array $attributes) => [
            'olt_id' => $olt->id,
        ]);
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customer->id,
        ]);
    }

    public function forSubscription(Subscription $subscription): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
        ]);
    }
}
