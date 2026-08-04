<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        $customer = Customer::factory()->create();
        $subscription = Subscription::factory()->forCustomer($customer)->create();

        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'created_by' => 1,
            'assigned_to' => null,
            'updated_by' => 1,
            'ticket_number' => fake()->unique()->bothify('TKT-######'),
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['Technical', 'Billing', 'Sales', 'Support', 'Complaint']),
            'sub_category' => null,
            'priority' => fake()->randomElement(TicketPriority::cases()),
            'status' => TicketStatus::OPEN,
            'due_at' => fake()->dateTimeBetween('now', '+7 days'),
            'resolved_at' => null,
            'closed_at' => null,
            'response_time_minutes' => null,
            'resolution_time_minutes' => null,
            'source' => fake()->randomElement(['Phone', 'Email', 'Web', 'App', 'Walk-in']),
            'related_invoice_id' => null,
            'related_payment_id' => null,
            'attachments' => null,
            'internal_notes' => null,
            'tags' => json_encode(['urgent', 'network']),
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

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $invoice->customer_id,
            'related_invoice_id' => $invoice->id,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::CLOSED,
            'closed_at' => now(),
            'resolved_at' => now(),
        ]);
    }
}