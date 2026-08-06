<?php

namespace Database\Factories;

use App\Enums\InventoryStatus;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        $purchasePrice = fake()->numberBetween(10000, 500000);
        $sellingPrice = (int) ($purchasePrice * fake()->numberBetween(110, 200) / 100);

        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'customer_id' => null,
            'subscription_id' => null,
            'reseller_id' => null,
            'created_by' => 1,
            'updated_by' => 1,
            'name' => fake()->words(3, true),
            'item_type' => fake()->randomElement(['router', 'onu', 'cable', 'switch', 'olt', 'sfp', 'accessory', 'other']),
            'category' => fake()->randomElement(['network', 'accessory', 'consumable', 'equipment']),
            'brand' => fake()->company(),
            'model' => fake()->bothify('Model-????'),
            'serial_number' => fake()->unique()->bothify('SN-########'),
            'imei' => null,
            'mac_address' => null,
            'barcode' => fake()->unique()->bothify('BC-########'),
            'asset_tag' => fake()->unique()->bothify('AT-########'),
            'status' => InventoryStatus::IN_STOCK,
            'warehouse' => fake()->randomElement(['Main', 'North', 'South', 'East', 'West']),
            'bin_location' => fake()->bothify('BIN-??-??'),
            'assigned_location' => null,
            'purchase_price' => $purchasePrice,
            'selling_price' => $sellingPrice,
            'purchase_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'purchase_invoice_id' => null,
            'supplier_id' => null,
            'warranty_start' => fake()->dateTimeBetween('-2 years', '-1 year'),
            'warranty_end' => fake()->dateTimeBetween('+1 month', '+2 years'),
            'warranty_months' => fake()->numberBetween(12, 36),
            'assigned_at' => null,
            'returned_at' => null,
            'assignment_notes' => null,
            'condition' => fake()->randomElement(['new', 'like_new', 'good', 'fair', 'poor']),
            'condition_notes' => null,
            'specifications' => null,
            'notes' => null,
        ];
    }

    public function assignedToCustomer(Customer $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customer->id,
            'status' => InventoryStatus::ASSIGNED,
            'assigned_at' => now(),
            'assigned_location' => $customer->service_address,
        ]);
    }

    public function assignedToSubscription(Subscription $subscription): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'status' => InventoryStatus::ASSIGNED,
            'assigned_at' => now(),
        ]);
    }

    public function faulty(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InventoryStatus::FAULTY,
            'condition' => 'poor',
            'condition_notes' => 'Device not powering on',
        ]);
    }
}
