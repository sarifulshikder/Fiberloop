<?php

namespace App\Events\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a customer's subscription is suspended due to non-payment.
 * Phase 7 (FreeRADIUS) will listen to this event to disable network access.
 */
class SubscriptionSuspended
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The customer whose subscription was suspended.
     */
    public Customer $customer;

    /**
     * The overdue invoice that triggered the suspension.
     */
    public Invoice $invoice;

    /**
     * Reason for suspension.
     */
    public string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(Customer $customer, Invoice $invoice, string $reason = 'Non-payment of invoice')
    {
        $this->customer = $customer;
        $this->invoice = $invoice;
        $this->reason = $reason;
    }
}
