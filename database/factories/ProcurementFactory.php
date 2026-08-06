<?php

namespace Database\Factories;

use App\Enums\ProcurementStatus;
use App\Models\Procurement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Procurement>
 */
class ProcurementFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(100000, 5000000);
        $taxAmount = (int) ($subtotal * 0.15); // 15% VAT
        $shippingCost = fake()->numberBetween(10000, 200000);
        $totalAmount = $subtotal + $taxAmount + $shippingCost;

        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'po_number' => fake()->unique()->bothify('PO-########'),
            'supplier_id' => null,
            'created_by' => 1,
            'approved_by' => null,
            'updated_by' => 1,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(ProcurementStatus::cases()),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'order_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'expected_delivery_date' => fake()->dateTimeBetween('+1 week', '+2 months'),
            'actual_delivery_date' => null,
            'approved_at' => null,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'shipping_cost' => $shippingCost,
            'total_amount' => $totalAmount,
            'currency' => 'BDT',
            'tracking_number' => null,
            'shipping_method' => fake()->randomElement(['courier', 'direct', 'pickup', 'freight']),
            'notes' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcurementStatus::DRAFT,
        ]);
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcurementStatus::PENDING_APPROVAL,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcurementStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function ordered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcurementStatus::ORDERED,
            'approved_at' => now(),
            'order_date' => now(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcurementStatus::RECEIVED,
            'approved_at' => now(),
            'order_date' => now()->subDays(7),
            'actual_delivery_date' => now(),
        ]);
    }
}
