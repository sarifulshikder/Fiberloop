<?php

namespace App\Events\Billing;

use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a customer's subscription is reactivated after payment.
 * Phase 7 (FreeRADIUS) will listen to this event to re-enable network access.
 */
class SubscriptionReactivated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The customer whose subscription was reactivated.
     */
    public Customer $customer;

    /**
     * The payment that triggered the reactivation.
     */
    public ?Payment $payment;

    /**
     * Reason for reactivation.
     */
    public string $reason;

    /**
     * Create a new event instance.
     */
    public function __construct(Customer $customer, ?Payment $payment = null, string $reason = 'Payment received')
    {
        $this->customer = $customer;
        $this->payment = $payment;
        $this->reason = $reason;
    }
}
