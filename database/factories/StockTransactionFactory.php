<?php

namespace Database\Factories;

use App\Enums\InventoryStatus;
use App\Enums\StockTransactionReason;
use App\Enums\StockTransactionType;
use App\Models\InventoryItem;
use App\Models\StockTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransaction>
 */
class StockTransactionFactory extends Factory
{
    public function definition(): array
    {
        $transactionType = fake()->randomElement(StockTransactionType::cases());
        $reason = fake()->randomElement(StockTransactionReason::cases());

        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'inventory_item_id' => InventoryItem::factory(),
            'user_id' => 1,
            'customer_id' => null,
            'field_job_id' => null,
            'subscription_id' => null,
            'created_by' => 1,
            'updated_by' => 1,
            'transaction_type' => $transactionType,
            'reason' => $reason,
            'reference_number' => fake()->unique()->bothify('REF-########'),
            'notes' => fake()->sentence(),
            'previous_status' => null,
            'previous_location' => null,
            'previous_holder_id' => null,
            'new_status' => null,
            'new_location' => null,
            'new_holder_id' => null,
            'quantity' => fake()->numberBetween(1, 10),
            'unit_cost' => fake()->numberBetween(10000, 500000),
            'total_cost' => function (array $attributes) {
                return $attributes['unit_cost'] * $attributes['quantity'];
            },
        ];
    }

    public function receipt(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => StockTransactionType::RECEIPT,
            'reason' => fake()->randomElement([
                StockTransactionReason::PURCHASE,
                StockTransactionReason::STOCK_ADJUSTMENT,
            ]),
            'previous_status' => null,
            'new_status' => InventoryStatus::IN_STOCK,
            'quantity' => fake()->numberBetween(1, 100),
        ]);
    }

    public function issue(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => StockTransactionType::ISSUE,
            'reason' => fake()->randomElement([
                StockTransactionReason::NEW_INSTALLATION,
                StockTransactionReason::REPLACEMENT,
                StockTransactionReason::MAINTENANCE,
                StockTransactionReason::TECHNICIAN_CHECKOUT,
            ]),
            'previous_status' => InventoryStatus::IN_STOCK,
            'new_status' => InventoryStatus::ASSIGNED,
            'quantity' => 1,
        ]);
    }

    public function return(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_type' => StockTransactionType::RETURN,
            'reason' => fake()->randomElement([
                StockTransactionReason::CUSTOMER_TERMINATION,
                StockTransactionReason::FAULTY,
                StockTransactionReason::TECHNICIAN_CHECKIN,
            ]),
            'previous_status' => InventoryStatus::ASSIGNED,
            'new_status' => InventoryStatus::NEEDS_INSPECTION,
            'quantity' => 1,
        ]);
    }

    public function forItem(InventoryItem $item): static
    {
        return $this->state(fn (array $attributes) => [
            'inventory_item_id' => $item->id,
        ]);
    }
}
