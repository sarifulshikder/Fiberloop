<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'alternate_phone' => $this->alternate_phone,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'service_address' => $this->service_address,
            'service_latitude' => $this->service_latitude,
            'service_longitude' => $this->service_longitude,
            'billing_address' => $this->billing_address,
            'area' => $this->area,
            'zone' => $this->zone,
            'connection_type' => $this->connection_type?->value,
            'status' => $this->status?->value,
            'radius_username' => $this->radius_username,
            'static_ip' => $this->static_ip,
            'mac_address' => $this->mac_address,
            'wallet_balance' => $this->wallet_balance / 100, // Convert from poysha to BDT
            'activated_at' => $this->activated_at?->toDateTimeString(),
            'suspended_at' => $this->suspended_at?->toDateTimeString(),
            'terminated_at' => $this->terminated_at?->toDateTimeString(),
            'suspension_reason' => $this->suspension_reason,
            'termination_reason' => $this->termination_reason,
            'fcm_token' => $this->fcm_token,
            'promotional_sms_opt_in' => $this->promotional_sms_opt_in,
            'promotional_email_opt_in' => $this->promotional_email_opt_in,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'subscriptions' => SubscriptionResource::collection($this->whenLoaded('subscriptions')),
            'current_subscription' => new SubscriptionResource($this->whenLoaded('currentSubscription')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
        ];
    }
}
