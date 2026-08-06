<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'invoice_id' => $this->invoice_id,
            'customer_id' => $this->customer_id,
            'reseller_id' => $this->reseller_id,
            'collected_by' => $this->collected_by,
            'amount' => $this->amount / 100, // Convert from poysha to BDT
            'fee_amount' => $this->fee_amount / 100,
            'net_amount' => $this->net_amount / 100, // Convert from poysha to BDT
            'method' => $this->method?->value,
            'status' => $this->status?->value,
            'gateway_reference' => $this->gateway_reference,
            'gateway_response' => $this->gateway_response,
            'paid_at' => $this->paid_at?->toDateTimeString(),
            'notes' => $this->notes,
            'failure_reason' => $this->failure_reason,
            'receipt_path' => $this->receipt_path,
            'split_from_payment_id' => $this->split_from_payment_id,
            'is_partial' => $this->is_partial,
            'is_wallet_topup' => $this->is_wallet_topup,
            'applied_to_invoice' => $this->applied_to_invoice,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
