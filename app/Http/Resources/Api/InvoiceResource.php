<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'invoice_number' => $this->invoice_number,
            'customer_id' => $this->customer_id,
            'subscription_id' => $this->subscription_id,
            'reseller_id' => $this->reseller_id,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'subtotal' => $this->subtotal / 100, // Convert from poysha to BDT
            'tax_amount' => $this->tax_amount / 100,
            'tax_rate' => $this->tax_rate / 100,
            'discount_amount' => $this->discount_amount / 100,
            'total' => $this->total / 100, // Convert from poysha to BDT
            'paid_amount' => $this->paid_amount / 100,
            'outstanding_amount' => $this->outstanding_amount / 100,
            'status' => $this->status?->value,
            'is_prorated' => $this->is_prorated,
            'proration_amount' => $this->proration_amount / 100,
            'billing_type' => $this->billing_type,
            'sent_at' => $this->sent_at?->toDateTimeString(),
            'paid_at' => $this->paid_at?->toDateTimeString(),
            'cancelled_at' => $this->cancelled_at?->toDateTimeString(),
            'cancellation_reason' => $this->cancellation_reason,
            'pdf_path' => $this->pdf_path,
            'pdf_generated' => $this->pdf_generated,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'subscription' => new SubscriptionResource($this->whenLoaded('subscription')),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
