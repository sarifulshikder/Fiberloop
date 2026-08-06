<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        $invoice = Invoice::factory()->create();
        $amount = $invoice->outstanding_amount;
        $feeAmount = fake()->numberBetween(0, 10000);
        $netAmount = $amount - $feeAmount;

        return [
            'tenant_id' => null,
            'uuid' => fake()->uuid(),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'reseller_id' => null,
            'created_by' => 1,
            'updated_by' => 1,
            'collected_by' => null,
            'amount' => $amount,
            'fee_amount' => $feeAmount,
            'net_amount' => $netAmount,
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'status' => PaymentStatus::PENDING,
            'gateway_reference' => null,
            'gateway_response' => null,
            'paid_at' => null,
            'notes' => null,
            'failure_reason' => null,
            'receipt_path' => null,
        ];
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'amount' => $invoice->outstanding_amount,
        ]);
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customer->id,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::COMPLETED,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::FAILED,
            'failure_reason' => 'Insufficient funds',
        ]);
    }
}
