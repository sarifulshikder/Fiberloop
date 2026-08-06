<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageChangeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'customer_id' => $this->customer_id,
            'subscription_id' => $this->subscription_id,
            'current_package_id' => $this->current_package_id,
            'requested_package_id' => $this->requested_package_id,
            'current_package' => new PackageResource($this->whenLoaded('currentPackage')),
            'requested_package' => new PackageResource($this->whenLoaded('requestedPackage')),
            'change_type' => $this->change_type,
            'status' => $this->status,
            'effective_date' => $this->effective_date?->toDateString(),
            'notes' => $this->notes,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'approved_by' => $this->approved_by,
            'approved_by_user' => $this->whenLoaded('approvedBy') ? $this->approvedBy->name : null,
            'cancelled_at' => $this->cancelled_at?->toDateTimeString(),
            'cancelled_by' => $this->cancelled_by,
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
        ];
    }
}
