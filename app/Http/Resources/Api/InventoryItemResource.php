<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'item_type' => $this->item_type,
            'category' => $this->category,
            'brand' => $this->brand,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'imei' => $this->imei,
            'mac_address' => $this->mac_address,
            'barcode' => $this->barcode,
            'asset_tag' => $this->asset_tag,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_color' => $this->status?->color(),
            'warehouse' => $this->warehouse,
            'bin_location' => $this->bin_location,
            'assigned_location' => $this->assigned_location,
            'purchase_price' => $this->purchase_price / 100, // Convert from poysha to BDT
            'selling_price' => $this->selling_price / 100, // Convert from poysha to BDT
            'purchase_date' => $this->purchase_date?->toDateString(),
            'purchase_invoice_id' => $this->purchase_invoice_id,
            'supplier' => new UserResource($this->whenLoaded('supplier')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'reseller' => new ResellerResource($this->whenLoaded('reseller')),
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
            'warranty_start' => $this->warranty_start?->toDateString(),
            'warranty_end' => $this->warranty_end?->toDateString(),
            'warranty_months' => $this->warranty_months,
            'warranty_expiring_soon' => $this->warranty_end && $this->warranty_end->isBefore(now()->addDays(30)) && $this->warranty_end->isAfter(now()),
            'warranty_expired' => $this->warranty_end && $this->warranty_end->isBefore(now()),
            'assigned_at' => $this->assigned_at?->toDateTimeString(),
            'returned_at' => $this->returned_at?->toDateTimeString(),
            'assignment_notes' => $this->assignment_notes,
            'condition' => $this->condition,
            'condition_notes' => $this->condition_notes,
            'specifications' => $this->specifications,
            'notes' => $this->notes,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'transactions' => StockTransactionResource::collection($this->whenLoaded('stockTransactions')),
            'transaction_count' => $this->stockTransactions()->count(),
        ];
    }
}
