<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Customer Status State Machine
 *
 * Manages transitions between customer statuses with validation and logging.
 * All money-moving actions are queued jobs per AGENTS.md, but status transitions
 * are synchronous and logged.
 */
class CustomerStatusManager
{
    /**
     * Allowed transitions map: from_status => [allowed_to_statuses]
     */
    private array $allowedTransitions = [
        'pending' => [
            CustomerStatus::ACTIVE,
            CustomerStatus::TERMINATED,
        ],
        'active' => [
            CustomerStatus::SUSPENDED,
            CustomerStatus::TERMINATED,
        ],
        'suspended' => [
            CustomerStatus::ACTIVE,
            CustomerStatus::TERMINATED,
        ],
        'terminated' => [
            // Cannot transition out of terminated
        ],
    ];

    /**
     * Transition a customer from one status to another
     *
     * @param Customer $customer
     * @param CustomerStatus $newStatus
     * @param User $actor
     * @param string|null $reason
     * @return Customer
     * @throws \InvalidArgumentException
     */
    public function transition(
        Customer $customer,
        CustomerStatus $newStatus,
        User $actor,
        ?string $reason = null
    ): Customer {
        $oldStatus = $customer->status;

        // Check if transition is allowed
        if (!$this->isTransitionAllowed($oldStatus, $newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition customer from '{$oldStatus->value}' to '{$newStatus->value}'"
            );
        }

        // Prevent duplicate status
        if ($oldStatus === $newStatus) {
            return $customer;
        }

        // Update the status
        $customer->forceFill(['status' => $newStatus])->save();

        // Update timestamps based on status
        $this->updateStatusTimestamps($customer, $newStatus);

        // Log the transition
        $this->logTransition($customer, $actor, $oldStatus, $newStatus, $reason);

        return $customer->fresh();
    }

    /**
     * Check if a transition from one status to another is allowed
     */
    public function isTransitionAllowed(CustomerStatus $from, CustomerStatus $to): bool
    {
        return in_array($to, $this->allowedTransitions[$from->value] ?? []);
    }

    /**
     * Get allowed transitions from a given status
     */
    public function getAllowedTransitions(CustomerStatus $from): array
    {
        return $this->allowedTransitions[$from->value] ?? [];
    }

    /**
     * Activate a pending customer
     */
    public function activate(Customer $customer, User $actor, ?string $reason = null): Customer
    {
        return $this->transition($customer, CustomerStatus::ACTIVE, $actor, $reason);
    }

    /**
     * Suspend an active customer
     */
    public function suspend(Customer $customer, User $actor, ?string $reason = null): Customer
    {
        $customer->forceFill([
            'status' => CustomerStatus::SUSPENDED,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ])->save();

        $this->logTransition(
            $customer,
            $actor,
            CustomerStatus::ACTIVE,
            CustomerStatus::SUSPENDED,
            $reason
        );

        return $customer->fresh();
    }

    /**
     * Reactivate a suspended customer
     */
    public function reactivate(Customer $customer, User $actor, ?string $reason = null): Customer
    {
        $customer->forceFill([
            'status' => CustomerStatus::ACTIVE,
            'suspended_at' => null,
            'suspension_reason' => null,
        ])->save();

        $this->logTransition(
            $customer,
            $actor,
            CustomerStatus::SUSPENDED,
            CustomerStatus::ACTIVE,
            $reason
        );

        return $customer->fresh();
    }

    /**
     * Terminate a customer (from any status except terminated)
     */
    public function terminate(Customer $customer, User $actor, ?string $reason = null): Customer
    {
        $oldStatus = $customer->status;

        if ($oldStatus === CustomerStatus::TERMINATED) {
            return $customer;
        }

        $customer->forceFill([
            'status' => CustomerStatus::TERMINATED,
            'terminated_at' => now(),
            'termination_reason' => $reason,
        ])->save();

        $this->logTransition($customer, $actor, $oldStatus, CustomerStatus::TERMINATED, $reason);

        return $customer->fresh();
    }

    /**
     * Update status-specific timestamps
     */
    private function updateStatusTimestamps(Customer $customer, CustomerStatus $newStatus): void
    {
        switch ($newStatus) {
            case CustomerStatus::ACTIVE:
                if (!$customer->activated_at) {
                    $customer->forceFill(['activated_at' => now()])->save();
                }
                // Clear suspension/termination timestamps when reactivating
                if ($customer->status === CustomerStatus::SUSPENDED) {
                    $customer->forceFill([
                        'suspended_at' => null,
                        'suspension_reason' => null,
                    ])->save();
                }
                if ($customer->status === CustomerStatus::TERMINATED) {
                    $customer->forceFill([
                        'terminated_at' => null,
                        'termination_reason' => null,
                    ])->save();
                }
                break;

            case CustomerStatus::SUSPENDED:
                $customer->forceFill(['suspended_at' => now()])->save();
                break;

            case CustomerStatus::TERMINATED:
                $customer->forceFill(['terminated_at' => now()])->save();
                break;
        }
    }

    /**
     * Log a status transition
     */
    private function logTransition(
        Customer $customer,
        User $actor,
        CustomerStatus $oldStatus,
        CustomerStatus $newStatus,
        ?string $reason
    ): void {
        $properties = [
            'old_status' => $oldStatus->value,
            'new_status' => $newStatus->value,
            'customer_id' => $customer->id,
            'customer_uuid' => $customer->uuid,
            'customer_name' => $customer->full_name,
        ];

        if ($reason) {
            $properties['reason'] = $reason;
        }

        activity()
            ->by($actor)
            ->on($customer)
            ->withProperties($properties)
            ->log("Customer status changed: {$oldStatus->value} → {$newStatus->value}");

        Log::info("Customer status transition", [
            'customer_id' => $customer->id,
            'from' => $oldStatus->value,
            'to' => $newStatus->value,
            'actor_id' => $actor->id,
            'reason' => $reason,
        ]);
    }
}
