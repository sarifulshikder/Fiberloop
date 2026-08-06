<?php

namespace App\Services;

use App\Enums\InventoryStatus;
use App\Enums\StockTransactionReason;
use App\Enums\StockTransactionType;
use App\Events\InventoryItemAssigned;
use App\Events\InventoryItemReturned;
use App\Events\InventoryItemStatusChanged;
use App\Models\Customer;
use App\Models\FieldJob;
use App\Models\InventoryItem;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use App\Models\StockTransaction;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryService
{
    /**
     * Receive inventory items from a procurement.
     */
    public function receiveItems(
        Procurement $procurement,
        array $itemsData,
        User $receivedBy,
        ?string $location = null
    ): array {
        $receivedItems = [];
        $transactions = [];

        DB::transaction(function () use (&$receivedItems, &$transactions, $procurement, $itemsData, $receivedBy, $location) {
            foreach ($itemsData as $itemData) {
                // Create or find inventory item
                $inventoryItem = null;

                if (isset($itemData['inventory_item_id'])) {
                    $inventoryItem = InventoryItem::find($itemData['inventory_item_id']);
                }

                if (!$inventoryItem) {
                    $inventoryItem = InventoryItem::create([
                        'tenant_id' => $procurement->tenant_id,
                        'uuid' => (string) Str::orderedUuid(),
                        'name' => $itemData['name'] ?? 'Unnamed Item',
                        'item_type' => $itemData['item_type'],
                        'category' => $itemData['category'] ?? null,
                        'brand' => $itemData['brand'] ?? null,
                        'model' => $itemData['model'] ?? null,
                        'serial_number' => $itemData['serial_number'] ?? null,
                        'mac_address' => $itemData['mac_address'] ?? null,
                        'purchase_price' => $itemData['unit_price'] ?? 0,
                        'purchase_date' => now()->toDateString(),
                        'purchase_invoice_id' => $procurement->id,
                        'supplier_id' => $procurement->supplier_id,
                        'status' => InventoryStatus::IN_STOCK,
                        'warehouse' => $location,
                        'bin_location' => $itemData['bin_location'] ?? null,
                        'created_by' => $receivedBy->id,
                        'updated_by' => $receivedBy->id,
                    ]);
                }

                // Update the procurement item as received
                $procurementItem = ProcurementItem::where('procurement_id', $procurement->id)
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->first();

                if ($procurementItem) {
                    $newReceivedQty = ($procurementItem->received_quantity ?? 0) + ($itemData['quantity'] ?? 1);
                    $procurementItem->update([
                        'received_quantity' => $newReceivedQty,
                        'status' => $newReceivedQty >= $procurementItem->quantity
                            ? ProcurementItemStatus::RECEIVED
                            : ProcurementItemStatus::PARTIALLY_RECEIVED,
                    ]);
                }

                // Create stock transaction
                $transaction = StockTransaction::create([
                    'tenant_id' => $procurement->tenant_id,
                    'uuid' => (string) Str::orderedUuid(),
                    'inventory_item_id' => $inventoryItem->id,
                    'user_id' => $receivedBy->id,
                    'created_by' => $receivedBy->id,
                    'transaction_type' => StockTransactionType::RECEIPT,
                    'reason' => StockTransactionReason::PURCHASE,
                    'reference_number' => $procurement->po_number,
                    'notes' => 'Received from PO #' . $procurement->po_number,
                    'previous_status' => null,
                    'previous_location' => null,
                    'previous_holder_id' => null,
                    'new_status' => InventoryStatus::IN_STOCK,
                    'new_location' => $location,
                    'new_holder_id' => null,
                    'quantity' => $itemData['quantity'] ?? 1,
                    'unit_cost' => $itemData['unit_price'] ?? 0,
                    'total_cost' => ($itemData['unit_price'] ?? 0) * ($itemData['quantity'] ?? 1),
                ]);

                // Update inventory item with received info
                if ($inventoryItem->status !== InventoryStatus::IN_STOCK) {
                    $inventoryItem->update([
                        'status' => InventoryStatus::IN_STOCK,
                        'warehouse' => $location,
                        'purchase_date' => now()->toDateString(),
                        'purchase_invoice_id' => $procurement->id,
                    ]);

                    event(new InventoryItemStatusChanged($inventoryItem, null, InventoryStatus::IN_STOCK));
                }

                $receivedItems[] = $inventoryItem;
                $transactions[] = $transaction;
            }

            // Update procurement status if all items received
            $allReceived = ProcurementItem::where('procurement_id', $procurement->id)
                ->where('status', '!=', ProcurementItemStatus::RECEIVED->value)
                ->doesntExist();

            if ($allReceived && $procurement->status !== ProcurementStatus::RECEIVED) {
                $procurement->update([
                    'status' => ProcurementStatus::RECEIVED,
                    'actual_delivery_date' => now()->toDateString(),
                ]);
            }
        });

        return [
            'items' => $receivedItems,
            'transactions' => $transactions,
        ];
    }

    /**
     * Issue an inventory item to a customer (new installation).
     */
    public function issueToCustomer(
        InventoryItem $inventoryItem,
        Customer $customer,
        Subscription $subscription,
        User $issuedBy,
        ?FieldJob $fieldJob = null,
        ?string $notes = null
    ): StockTransaction {
        $previousStatus = $inventoryItem->status;
        $previousLocation = $inventoryItem->warehouse;
        $previousHolderId = $inventoryItem->assigned_to;

        $transaction = DB::transaction(function () use ($inventoryItem, $customer, $subscription, $issuedBy, $fieldJob, $notes, $previousStatus, $previousLocation, $previousHolderId) {
            $transaction = StockTransaction::create([
                'tenant_id' => $inventoryItem->tenant_id,
                'uuid' => (string) Str::orderedUuid(),
                'inventory_item_id' => $inventoryItem->id,
                'user_id' => $issuedBy->id,
                'customer_id' => $customer->id,
                'subscription_id' => $subscription->id,
                'field_job_id' => $fieldJob?->id,
                'created_by' => $issuedBy->id,
                'transaction_type' => StockTransactionType::ISSUE,
                'reason' => StockTransactionReason::NEW_INSTALLATION,
                'reference_number' => $fieldJob?->uuid ?? $subscription->uuid,
                'notes' => $notes ?? 'Issued for new installation',
                'previous_status' => $previousStatus,
                'previous_location' => $previousLocation,
                'previous_holder_id' => $previousHolderId,
                'new_status' => InventoryStatus::ASSIGNED,
                'new_location' => $customer->service_address,
                'new_holder_id' => $customer->id,
                'quantity' => 1,
            ]);

            $inventoryItem->update([
                'customer_id' => $customer->id,
                'subscription_id' => $subscription->id,
                'status' => InventoryStatus::ASSIGNED,
                'assigned_at' => now(),
                'assigned_location' => $customer->service_address,
                'warehouse' => null,
                'bin_location' => null,
                'updated_by' => $issuedBy->id,
                'assignment_notes' => $notes,
            ]);

            event(new InventoryItemAssigned($inventoryItem, $customer, $subscription, $fieldJob));
            event(new InventoryItemStatusChanged($inventoryItem, $previousStatus, InventoryStatus::ASSIGNED));

            return $transaction;
        });

        return $transaction;
    }

    /**
     * Issue an inventory item to a technician.
     */
    public function issueToTechnician(
        InventoryItem $inventoryItem,
        User $technician,
        User $issuedBy,
        ?FieldJob $fieldJob = null,
        ?string $notes = null
    ): StockTransaction {
        $previousStatus = $inventoryItem->status;
        $previousLocation = $inventoryItem->warehouse;
        $previousHolderId = $inventoryItem->assigned_to;

        $transaction = DB::transaction(function () use ($inventoryItem, $technician, $issuedBy, $fieldJob, $notes, $previousStatus, $previousLocation, $previousHolderId) {
            $transaction = StockTransaction::create([
                'tenant_id' => $inventoryItem->tenant_id,
                'uuid' => (string) Str::orderedUuid(),
                'inventory_item_id' => $inventoryItem->id,
                'user_id' => $issuedBy->id,
                'field_job_id' => $fieldJob?->id,
                'created_by' => $issuedBy->id,
                'transaction_type' => StockTransactionType::ISSUE,
                'reason' => StockTransactionReason::TECHNICIAN_CHECKOUT,
                'reference_number' => $fieldJob?->uuid,
                'notes' => $notes ?? 'Checked out to technician',
                'previous_status' => $previousStatus,
                'previous_location' => $previousLocation,
                'previous_holder_id' => $previousHolderId,
                'new_status' => InventoryStatus::ASSIGNED,
                'new_location' => null,
                'new_holder_id' => $technician->id,
                'quantity' => 1,
            ]);

            $inventoryItem->update([
                'status' => InventoryStatus::ASSIGNED,
                'assigned_at' => now(),
                'warehouse' => null,
                'bin_location' => null,
                'updated_by' => $issuedBy->id,
                'assignment_notes' => $notes,
            ]);

            event(new InventoryItemStatusChanged($inventoryItem, $previousStatus, InventoryStatus::ASSIGNED));

            return $transaction;
        });

        return $transaction;
    }

    /**
     * Return an inventory item from a customer.
     */
    public function returnFromCustomer(
        InventoryItem $inventoryItem,
        Customer $customer,
        User $returnedBy,
        string $reason,
        ?string $condition = null,
        ?string $conditionNotes = null,
        ?string $location = null
    ): StockTransaction {
        $previousStatus = $inventoryItem->status;
        $previousLocation = $inventoryItem->assigned_location;
        $previousHolderId = $inventoryItem->customer_id;

        // Determine new status based on reason
        $newStatus = match ($reason) {
            'faulty', 'damage' => InventoryStatus::NEEDS_INSPECTION,
            'end_of_life' => InventoryStatus::RETIRED,
            default => InventoryStatus::IN_STOCK,
        };

        $transaction = DB::transaction(function () use ($inventoryItem, $customer, $returnedBy, $reason, $condition, $conditionNotes, $location, $previousStatus, $previousLocation, $previousHolderId, $newStatus) {
            $transaction = StockTransaction::create([
                'tenant_id' => $inventoryItem->tenant_id,
                'uuid' => (string) Str::orderedUuid(),
                'inventory_item_id' => $inventoryItem->id,
                'user_id' => $returnedBy->id,
                'customer_id' => $customer->id,
                'created_by' => $returnedBy->id,
                'transaction_type' => StockTransactionType::RETURN,
                'reason' => StockTransactionReason::from($reason),
                'reference_number' => $customer->uuid,
                'notes' => 'Returned from customer: ' . ($conditionNotes ?? $reason),
                'previous_status' => $previousStatus,
                'previous_location' => $previousLocation,
                'previous_holder_id' => $previousHolderId,
                'new_status' => $newStatus,
                'new_location' => $location,
                'new_holder_id' => null,
                'quantity' => 1,
            ]);

            $inventoryItem->update([
                'customer_id' => null,
                'subscription_id' => null,
                'status' => $newStatus,
                'warehouse' => $location,
                'bin_location' => null,
                'returned_at' => now(),
                'assigned_at' => null,
                'assigned_location' => null,
                'condition' => $condition ?? $inventoryItem->condition,
                'condition_notes' => $conditionNotes,
                'updated_by' => $returnedBy->id,
            ]);

            event(new InventoryItemReturned($inventoryItem, $customer, $reason, $condition, $conditionNotes));
            event(new InventoryItemStatusChanged($inventoryItem, $previousStatus, $newStatus));

            return $transaction;
        });

        return $transaction;
    }

    /**
     * Return an inventory item from a technician.
     */
    public function returnFromTechnician(
        InventoryItem $inventoryItem,
        User $technician,
        User $returnedBy,
        ?string $location = null,
        ?string $condition = null,
        ?string $conditionNotes = null
    ): StockTransaction {
        $previousStatus = $inventoryItem->status;
        $previousHolderId = $inventoryItem->assigned_to;

        $transaction = DB::transaction(function () use ($inventoryItem, $technician, $returnedBy, $location, $condition, $conditionNotes, $previousStatus, $previousHolderId) {
            $transaction = StockTransaction::create([
                'tenant_id' => $inventoryItem->tenant_id,
                'uuid' => (string) Str::orderedUuid(),
                'inventory_item_id' => $inventoryItem->id,
                'user_id' => $returnedBy->id,
                'created_by' => $returnedBy->id,
                'transaction_type' => StockTransactionType::RETURN,
                'reason' => StockTransactionReason::TECHNICIAN_CHECKIN,
                'reference_number' => null,
                'notes' => 'Checked in by technician',
                'previous_status' => $previousStatus,
                'previous_location' => null,
                'previous_holder_id' => $previousHolderId,
                'new_status' => InventoryStatus::IN_STOCK,
                'new_location' => $location,
                'new_holder_id' => null,
                'quantity' => 1,
            ]);

            $inventoryItem->update([
                'status' => InventoryStatus::IN_STOCK,
                'warehouse' => $location,
                'bin_location' => null,
                'assigned_at' => null,
                'assigned_location' => null,
                'condition' => $condition ?? $inventoryItem->condition,
                'condition_notes' => $conditionNotes,
                'updated_by' => $returnedBy->id,
            ]);

            event(new InventoryItemStatusChanged($inventoryItem, $previousStatus, InventoryStatus::IN_STOCK));

            return $transaction;
        });

        return $transaction;
    }

    /**
     * Flag equipment for return when customer is terminated.
     */
    public function flagForReturnOnTermination(Customer $customer, User $flaggedBy): array
    {
        $items = InventoryItem::where('customer_id', $customer->id)
            ->whereIn('status', [InventoryStatus::ASSIGNED])
            ->get();

        $flaggedItems = [];

        DB::transaction(function () use (&$flaggedItems, $items, $customer, $flaggedBy) {
            foreach ($items as $item) {
                $previousStatus = $item->status;

                // Create a return flag transaction
                $transaction = StockTransaction::create([
                    'tenant_id' => $item->tenant_id,
                    'uuid' => (string) Str::orderedUuid(),
                    'inventory_item_id' => $item->id,
                    'user_id' => $flaggedBy->id,
                    'customer_id' => $customer->id,
                    'created_by' => $flaggedBy->id,
                    'transaction_type' => StockTransactionType::TRANSFER,
                    'reason' => StockTransactionReason::CUSTOMER_TERMINATION,
                    'reference_number' => $customer->uuid,
                    'notes' => 'Flagged for return due to customer termination',
                    'previous_status' => $previousStatus,
                    'previous_location' => $item->assigned_location,
                    'previous_holder_id' => $customer->id,
                    'new_status' => InventoryStatus::ASSIGNED,
                    'new_location' => null,
                    'new_holder_id' => null,
                    'quantity' => 1,
                ]);

                // Mark the item as needing inspection when returned
                // But keep it assigned for now until physically returned
                $item->update([
                    'notes' => ($item->notes ? $item->notes . '\n' : '') .
                        'FLAGGED FOR RETURN: Customer terminated on ' . now()->toDateTimeString(),
                    'updated_by' => $flaggedBy->id,
                ]);

                $flaggedItems[] = [
                    'item' => $item,
                    'transaction' => $transaction,
                ];
            }
        });

        return $flaggedItems;
    }

    /**
     * Move equipment from needs_inspection to available or retired.
     */
    public function resolveInspection(
        InventoryItem $inventoryItem,
        User $resolvedBy,
        InventoryStatus $newStatus,
        ?string $location = null,
        ?string $resolutionNotes = null
    ): StockTransaction {
        $previousStatus = $inventoryItem->status;

        if ($previousStatus !== InventoryStatus::NEEDS_INSPECTION) {
            throw new \InvalidArgumentException('Item must be in NEEDS_INSPECTION status');
        }

        $transaction = DB::transaction(function () use ($inventoryItem, $resolvedBy, $newStatus, $location, $resolutionNotes, $previousStatus) {
            $transaction = StockTransaction::create([
                'tenant_id' => $inventoryItem->tenant_id,
                'uuid' => (string) Str::orderedUuid(),
                'inventory_item_id' => $inventoryItem->id,
                'user_id' => $resolvedBy->id,
                'created_by' => $resolvedBy->id,
                'transaction_type' => StockTransactionType::ADJUSTMENT,
                'reason' => $newStatus === InventoryStatus::IN_STOCK
                    ? StockTransactionReason::MAINTENANCE
                    : StockTransactionReason::END_OF_LIFE,
                'reference_number' => null,
                'notes' => 'Inspection resolved: ' . ($resolutionNotes ?? ''),
                'previous_status' => $previousStatus,
                'previous_location' => $inventoryItem->warehouse,
                'previous_holder_id' => null,
                'new_status' => $newStatus,
                'new_location' => $location,
                'new_holder_id' => null,
                'quantity' => 1,
            ]);

            $inventoryItem->update([
                'status' => $newStatus,
                'warehouse' => $location,
                'condition_notes' => ($inventoryItem->condition_notes ? $inventoryItem->condition_notes . '\n' : '') .
                    ($resolutionNotes ?? ''),
                'updated_by' => $resolvedBy->id,
            ]);

            event(new InventoryItemStatusChanged($inventoryItem, $previousStatus, $newStatus));

            return $transaction;
        });

        return $transaction;
    }

    /**
     * Get stock level by item type.
     */
    public function getStockLevels(): Collection
    {
        return InventoryItem::query()
            ->selectRaw('item_type, COUNT(*) as count')
            ->whereIn('status', [InventoryStatus::IN_STOCK, InventoryStatus::NEEDS_INSPECTION])
            ->groupBy('item_type')
            ->orderBy('count', 'desc')
            ->get();
    }

    /**
     * Check for low stock and create alerts.
     */
    public function checkLowStock(array $thresholds): Collection
    {
        $alerts = collect();

        foreach ($thresholds as $itemType => $minQuantity) {
            $count = InventoryItem::where('item_type', $itemType)
                ->whereIn('status', [InventoryStatus::IN_STOCK])
                ->count();

            if ($count < $minQuantity) {
                $alerts->push([
                    'item_type' => $itemType,
                    'current_count' => $count,
                    'threshold' => $minQuantity,
                    'severity' => $count === 0 ? 'critical' : ($count < ($minQuantity / 2) ? 'warning' : 'low'),
                ]);
            }
        }

        return $alerts;
    }

    /**
     * Transfer an item between locations.
     */
    public function transferItem(
        InventoryItem $inventoryItem,
        User $transferredBy,
        string $newLocation,
        ?string $notes = null
    ): StockTransaction {
        $previousStatus = $inventoryItem->status;
        $previousLocation = $inventoryItem->warehouse;

        $transaction = DB::transaction(function () use ($inventoryItem, $transferredBy, $newLocation, $notes, $previousStatus, $previousLocation) {
            $transaction = StockTransaction::create([
                'tenant_id' => $inventoryItem->tenant_id,
                'uuid' => (string) Str::orderedUuid(),
                'inventory_item_id' => $inventoryItem->id,
                'user_id' => $transferredBy->id,
                'created_by' => $transferredBy->id,
                'transaction_type' => StockTransactionType::TRANSFER,
                'reason' => StockTransactionReason::STOCK_ADJUSTMENT,
                'reference_number' => null,
                'notes' => $notes ?? 'Transferred to new location',
                'previous_status' => $previousStatus,
                'previous_location' => $previousLocation,
                'previous_holder_id' => null,
                'new_status' => $previousStatus,
                'new_location' => $newLocation,
                'new_holder_id' => null,
                'quantity' => 1,
            ]);

            $inventoryItem->update([
                'warehouse' => $newLocation,
                'updated_by' => $transferredBy->id,
            ]);

            return $transaction;
        });

        return $transaction;
    }

    /**
     * Retire an inventory item.
     */
    public function retireItem(
        InventoryItem $inventoryItem,
        User $retiredBy,
        ?string $reason = null,
        ?string $notes = null
    ): StockTransaction {
        $previousStatus = $inventoryItem->status;

        $transaction = DB::transaction(function () use ($inventoryItem, $retiredBy, $reason, $notes, $previousStatus) {
            $transaction = StockTransaction::create([
                'tenant_id' => $inventoryItem->tenant_id,
                'uuid' => (string) Str::orderedUuid(),
                'inventory_item_id' => $inventoryItem->id,
                'user_id' => $retiredBy->id,
                'created_by' => $retiredBy->id,
                'transaction_type' => StockTransactionType::RETIREMENT,
                'reason' => $reason ? StockTransactionReason::from($reason) : StockTransactionReason::END_OF_LIFE,
                'reference_number' => null,
                'notes' => $notes ?? 'Item retired',
                'previous_status' => $previousStatus,
                'previous_location' => $inventoryItem->warehouse,
                'previous_holder_id' => null,
                'new_status' => InventoryStatus::RETIRED,
                'new_location' => null,
                'new_holder_id' => null,
                'quantity' => 1,
            ]);

            $inventoryItem->update([
                'status' => InventoryStatus::RETIRED,
                'warehouse' => null,
                'bin_location' => null,
                'assigned_at' => null,
                'returned_at' => null,
                'updated_by' => $retiredBy->id,
                'notes' => ($inventoryItem->notes ? $inventoryItem->notes . '\n' : '') .
                    'RETIRED: ' . ($notes ?? $reason),
            ]);

            event(new InventoryItemStatusChanged($inventoryItem, $previousStatus, InventoryStatus::RETIRED));

            return $transaction;
        });

        return $transaction;
    }

    /**
     * Get item history (all stock transactions).
     */
    public function getItemHistory(InventoryItem $inventoryItem): Collection
    {
        return StockTransaction::where('inventory_item_id', $inventoryItem->id)
            ->with(['user', 'customer', 'fieldJob', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get current stock level for a specific item type.
     */
    public function getItemTypeStock(string $itemType): array
    {
        return [
            'in_stock' => InventoryItem::where('item_type', $itemType)
                ->where('status', InventoryStatus::IN_STOCK)
                ->count(),
            'needs_inspection' => InventoryItem::where('item_type', $itemType)
                ->where('status', InventoryStatus::NEEDS_INSPECTION)
                ->count(),
            'assigned' => InventoryItem::where('item_type', $itemType)
                ->where('status', InventoryStatus::ASSIGNED)
                ->count(),
            'faulty' => InventoryItem::where('item_type', $itemType)
                ->where('status', InventoryStatus::FAULTY)
                ->count(),
            'retired' => InventoryItem::where('item_type', $itemType)
                ->where('status', InventoryStatus::RETIRED)
                ->count(),
            'total' => InventoryItem::where('item_type', $itemType)->count(),
        ];
    }
}
