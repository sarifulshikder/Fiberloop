<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $subscription = Subscription::factory()->create();
        $customer = $subscription->customer;
        $package = $subscription->package;

        $subtotal = $package->price;
        $taxAmount = (int) ($subtotal * $package->tax_rate / 10000);
        $total = $subtotal + $taxAmount;

        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'reseller_id' => null,
            'created_by' => \App\Models\User::factory(),
            'updated_by' => \App\Models\User::factory(),
            'invoice_number' => fake()->unique()->bothify('INV-######'),
            'period_start' => fake()->dateTimeBetween('-1 month', 'now'),
            'period_end' => fake()->dateTimeBetween('now', '+1 month'),
            'due_date' => fake()->dateTimeBetween('+1 week', '+1 month'),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => 0,
            'total' => $total,
            'paid_amount' => 0,
            'outstanding_amount' => $total,
            'status' => InvoiceStatus::DRAFT,
            'notes' => null,
            'sent_at' => null,
            'paid_at' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'pdf_path' => null,
            'pdf_generated' => false,
        ];
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
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::SENT,
            'sent_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::PAID,
            'paid_amount' => $attributes['total'],
            'outstanding_amount' => 0,
            'paid_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::OVERDUE,
            'due_date' => fake()->dateTimeBetween('-2 months', '-1 day'),
            'sent_at' => fake()->dateTimeBetween('-2 months', '-1 month'),
        ]);
    }
}
