<?php

namespace App\Events;

use App\Models\Customer;
use App\Models\FieldJob;
use App\Models\InventoryItem;
use App\Models\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an inventory item is assigned to a customer.
 * Used for tracking and notifications.
 */
class InventoryItemAssigned
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public InventoryItem $inventoryItem,
        public Customer $customer,
        public Subscription $subscription,
        public ?FieldJob $fieldJob = null
    ) {
    }
}
