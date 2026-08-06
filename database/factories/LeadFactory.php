<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'tenant_id' => 1,
            'uuid' => fake()->uuid(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'assigned_to' => User::factory(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->phoneNumber(),
            'alternate_phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(-90, 90, 6),
            'longitude' => fake()->longitude(-180, 180, 6),
            'area' => fake()->city(),
            'zone' => fake()->randomElement(['North', 'South', 'East', 'West', 'Central']),
            'status' => LeadStatus::NEW->value,
            'is_feasible' => null,
            'assigned_olt_id' => null,
            'assigned_network_device_id' => null,
            'feasibility_notes' => null,
            'site_survey_date' => null,
            'converted_customer_id' => null,
            'converted_at' => null,
            'source' => fake()->randomElement(['web', 'phone', 'referral', 'field', 'reseller', 'other']),
            'referral_code' => null,
            'notes' => null,
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
        ];
    }

    /**
     * Create a contacted lead.
     */
    public function contacted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::CONTACTED->value,
        ]);
    }

    /**
     * Create a site survey lead.
     */
    public function siteSurvey(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::SITE_SURVEY->value,
            'is_feasible' => null,
            'site_survey_date' => now(),
        ]);
    }

    /**
     * Create a converted lead.
     */
    public function converted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::CONVERTED->value,
            'is_feasible' => true,
            'converted_at' => now(),
        ]);
    }

    /**
     * Create a lost lead.
     */
    public function lost(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::LOST->value,
            'is_feasible' => false,
            'notes' => 'Lost due to: ' . fake()->sentence(),
        ]);
    }

    /**
     * Create a high priority lead.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    /**
     * Create a feasible lead.
     */
    public function feasible(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_feasible' => true,
            'feasibility_notes' => 'Address is within coverage area',
        ]);
    }

    /**
     * Create a not feasible lead.
     */
    public function notFeasible(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_feasible' => false,
            'feasibility_notes' => 'Address is outside coverage area',
        ]);
    }
}
