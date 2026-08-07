<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        $package = Package::factory()->create();
        $customer = Customer::factory()->create();
        $startDate = fake()->dateTimeBetween('-1 year', 'now');
        $nextBilling = fake()->dateTimeBetween('now', '+1 year');

        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'reseller_id' => null,
            'created_by' => \App\Models\User::factory(),
            'updated_by' => \App\Models\User::factory(),
            'start_date' => $startDate,
            'end_date' => fake()->dateTimeBetween($startDate, '+2 years'),
            'next_billing_date' => $nextBilling,
            'status' => SubscriptionStatus::ACTIVE,
            'monthly_price' => $package->price,
            'billing_cycle_discount' => 0,
            'final_price' => $package->price,
            'is_prorated' => false,
            'proration_amount' => 0,
            'proration_notes' => null,
            'assigned_ip' => null,
            'assigned_mac' => null,
            'assigned_port' => null,
            'assigned_vlan' => null,
            'network_device_id' => null,
            'olt_id' => null,
            'onu_id' => null,
            'activated_at' => now(),
            'expired_at' => null,
            'cancelled_at' => null,
            'suspended_at' => null,
            'cancellation_reason' => null,
            'suspension_reason' => null,
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customer->id,
        ]);
    }

    public function forPackage(Package $package): static
    {
        return $this->state(fn (array $attributes) => [
            'package_id' => $package->id,
            'monthly_price' => $package->price,
            'final_price' => $package->price,
        ]);
    }
}
