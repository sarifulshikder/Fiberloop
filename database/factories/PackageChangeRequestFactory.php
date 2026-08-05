<?php

namespace Database\Factories;

use App\Enums\PackageChangeRequestStatus;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PackageChangeRequest>
 */
class PackageChangeRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $changeTypes = ['upgrade', 'downgrade', 'change'];
        $changeType = fake()->randomElement($changeTypes);
        
        return [
            'tenant_id' => 1,
            'customer_id' => Customer::factory(),
            'current_package_id' => Package::factory(),
            'requested_package_id' => Package::factory(),
            'status' => PackageChangeRequestStatus::PENDING->value,
            'change_type' => $changeType,
            'requested_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
            'effective_date' => null,
            'proration_amount' => 0,
            'notes' => null,
            'approval_notes' => null,
        ];
    }

    /**
     * Create an approved request.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PackageChangeRequestStatus::APPROVED->value,
            'approved_by' => User::factory(),
            'approved_at' => now(),
            'effective_date' => now()->addDays(1),
        ]);
    }

    /**
     * Create a rejected request.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PackageChangeRequestStatus::REJECTED->value,
            'approval_notes' => 'Request rejected: ' . fake()->sentence(),
        ]);
    }

    /**
     * Create a completed request.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PackageChangeRequestStatus::COMPLETED->value,
            'approved_by' => User::factory(),
            'approved_at' => now()->subDays(1),
            'effective_date' => now(),
        ]);
    }

    /**
     * Create an upgrade request.
     */
    public function upgrade(): static
    {
        return $this->state(fn (array $attributes) => [
            'change_type' => 'upgrade',
        ]);
    }

    /**
     * Create a downgrade request.
     */
    public function downgrade(): static
    {
        return $this->state(fn (array $attributes) => [
            'change_type' => 'downgrade',
        ]);
    }
}
