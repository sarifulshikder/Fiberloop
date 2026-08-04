<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'customer_id' => null,
            'user_id' => null,
            'notifiable_type' => null,
            'notifiable_id' => null,
            'type' => fake()->randomElement(['invoice_reminder', 'payment_confirmed', 'ticket_updated', 'promotion', 'alert']),
            'channel' => fake()->randomElement(['sms', 'email', 'push', 'web']),
            'subject' => fake()->sentence(),
            'message' => fake()->paragraph(),
            'data' => json_encode(['key' => 'value']),
            'to_phone' => fake()->phoneNumber(),
            'to_email' => fake()->safeEmail(),
            'to_device_token' => null,
            'sent' => true,
            'delivered' => true,
            'failed' => false,
            'sent_at' => now(),
            'delivered_at' => now(),
            'gateway_response' => null,
            'gateway_reference' => null,
            'error_message' => null,
            'template_used' => fake()->randomElement(['default', 'urgent', 'promotional']),
            'attempt_count' => 1,
            'metadata' => null,
        ];
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customer->id,
            'to_phone' => $customer->phone,
            'to_email' => $customer->email,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'sent' => true,
            'delivered' => false,
            'failed' => true,
            'error_message' => 'Delivery failed',
            'attempt_count' => 3,
        ]);
    }

    public function sms(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'sms',
        ]);
    }

    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => 'email',
        ]);
    }
}