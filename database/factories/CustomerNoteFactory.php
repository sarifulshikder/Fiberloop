<?php

namespace Database\Factories;

use App\Enums\NoteType;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerNote>
 */
class CustomerNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'customer_id' => Customer::factory(),
            'type' => fake()->randomElement(NoteType::values()),
            'content' => fake()->paragraph(),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    /**
     * Create a support note.
     */
    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NoteType::SUPPORT->value,
            'content' => 'Support: ' . fake()->paragraph(),
        ]);
    }

    /**
     * Create a payment note.
     */
    public function payment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NoteType::PAYMENT->value,
            'content' => 'Payment: ' . fake()->paragraph(),
        ]);
    }

    /**
     * Create a technical note.
     */
    public function technical(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NoteType::TECHNICAL->value,
            'content' => 'Technical: ' . fake()->paragraph(),
        ]);
    }

    /**
     * Create a sales note.
     */
    public function sales(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => NoteType::SALES->value,
            'content' => 'Sales: ' . fake()->paragraph(),
        ]);
    }
}
