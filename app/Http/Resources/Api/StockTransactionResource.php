<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'inventory_item_id' => $this->inventory_item_id,
            'inventory_item' => new InventoryItemResource($this->whenLoaded('inventoryItem')),
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'field_job_id' => $this->field_job_id,
            'field_job' => $this->whenLoaded('fieldJob', function () {
                return [
                    'id' => $this->fieldJob->id,
                    'title' => $this->fieldJob->title,
                    'status' => $this->fieldJob->status,
                ];
            }),
            'subscription_id' => $this->subscription_id,
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
            'transaction_type' => $this->transaction_type?->value,
            'transaction_type_label' => $this->transaction_type?->label(),
            'transaction_type_color' => $this->transaction_type?->color(),
            'is_incoming' => $this->is_incoming,
            'is_outgoing' => $this->is_outgoing,
            'reason' => $this->reason?->value,
            'reason_label' => $this->reason?->label(),
            'reason_category' => $this->reason?->category(),
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost / 100, // Convert from poysha to BDT
            'total_cost' => $this->total_cost / 100, // Convert from poysha to BDT
            'previous_status' => $this->previous_status?->value,
            'previous_status_label' => $this->previous_status?->label(),
            'new_status' => $this->new_status?->value,
            'new_status_label' => $this->new_status?->label(),
            'previous_location' => $this->previous_location,
            'new_location' => $this->new_location,
            'previous_holder_id' => $this->previous_holder_id,
            'previous_holder' => new UserResource($this->whenLoaded('previousHolder')),
            'new_holder_id' => $this->new_holder_id,
            'new_holder' => new UserResource($this->whenLoaded('newHolder')),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'tenant_id' => $this->tenant_id,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),
        ];
    }
}
