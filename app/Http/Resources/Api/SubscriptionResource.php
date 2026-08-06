<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'customer_id' => $this->customer_id,
            'package_id' => $this->package_id,
            'package' => new PackageResource($this->whenLoaded('package')),
            'reseller_id' => $this->reseller_id,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'next_billing_date' => $this->next_billing_date?->toDateString(),
            'status' => $this->status?->value,
            'monthly_price' => $this->monthly_price / 100, // Convert from poysha to BDT
            'billing_cycle_discount' => $this->billing_cycle_discount / 100,
            'final_price' => $this->final_price / 100, // Convert from poysha to BDT
            'is_prorated' => $this->is_prorated,
            'proration_amount' => $this->proration_amount / 100,
            'proration_notes' => $this->proration_notes,
            'assigned_ip' => $this->assigned_ip,
            'assigned_mac' => $this->assigned_mac,
            'assigned_port' => $this->assigned_port,
            'assigned_vlan' => $this->assigned_vlan,
            'network_device_id' => $this->network_device_id,
            'olt_id' => $this->olt_id,
            'onu_id' => $this->onu_id,
            'activated_at' => $this->activated_at?->toDateTimeString(),
            'expired_at' => $this->expired_at?->toDateTimeString(),
            'cancelled_at' => $this->cancelled_at?->toDateTimeString(),
            'suspended_at' => $this->suspended_at?->toDateTimeString(),
            'cancellation_reason' => $this->cancellation_reason,
            'suspension_reason' => $this->suspension_reason,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'usage' => new UsageSummaryResource($this->whenLoaded('usage')),
        ];
    }
}
