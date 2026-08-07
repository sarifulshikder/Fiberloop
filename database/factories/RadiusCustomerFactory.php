<?php

namespace Database\Factories;

use App\Enums\ConnectionType;
use App\Models\Customer;
use App\Models\RadiusCustomer;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RadiusCustomer>
 */
class RadiusCustomerFactory extends Factory
{
    public function definition(): array
    {
        $customer = Customer::factory()->create();

        return [
            'tenant_id' => null,
            'customer_id' => $customer->id,
            'subscription_id' => null,
            'created_by' => \App\Models\User::factory(),
            'radius_username' => 'user_' . fake()->unique()->userName(),
            'radius_password' => fake()->password(),
            'radius_group' => 'default',
            'framed_ip_address' => fake()->unique()->ipv4(),
            'framed_ip_netmask' => '255.255.255.0',
            'framed_route' => '',
            'session_timeout' => 0,
            'idle_timeout' => 0,
            'max_input_octets' => 0,
            'max_output_octets' => 0,
            'max_total_octets' => 0,
            'max_download_speed' => 100000000,
            'max_upload_speed' => 100000000,
            'connection_type' => ConnectionType::PPPOE->value,
            'is_active' => true,
            'last_auth_at' => now(),
            'last_acct_start_at' => now(),
            'last_acct_stop_at' => null,
            'nas_ip_address' => fake()->ipv4(),
            'nas_port' => fake()->numberBetween(1, 1000),
            'session_id' => null,
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customer->id,
            'radius_username' => 'user_' . $customer->id . '_' . fake()->unique()->userName(),
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
