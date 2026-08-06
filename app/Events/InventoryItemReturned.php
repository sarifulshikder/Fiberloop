<?php

namespace App\Events;

use App\Models\Customer;
use App\Models\InventoryItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an inventory item is returned from a customer.
 * Used for tracking and notifications.
 */
class InventoryItemReturned
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
        public string $reason,
        public ?string $condition = null,
        public ?string $conditionNotes = null
    ) {
    }
}
