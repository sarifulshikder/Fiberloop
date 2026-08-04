<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(10000, 500000);
        $quantity = fake()->numberBetween(1, 12);
        $amount = $unitPrice * $quantity;

        return [
            'tenant_id' => null,
            'invoice_id' => Invoice::factory(),
            'description' => fake()->sentence(),
            'item_type' => fake()->randomElement(['service', 'fee', 'tax', 'discount', 'other']),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => $amount,
            'period_start' => fake()->dateTimeBetween('-1 month', 'now'),
            'period_end' => fake()->dateTimeBetween('now', '+1 month'),
            'metadata' => null,
        ];
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_id' => $invoice->id,
        ]);
    }
}