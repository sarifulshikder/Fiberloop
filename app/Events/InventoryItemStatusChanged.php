<?php

namespace App\Events;

use App\Enums\InventoryStatus;
use App\Models\InventoryItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an inventory item's status changes.
 * Used for tracking, notifications, and audit logging.
 */
class InventoryItemStatusChanged
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public InventoryItem $inventoryItem,
        public ?InventoryStatus $previousStatus,
        public InventoryStatus $newStatus
    ) {
    }
}
