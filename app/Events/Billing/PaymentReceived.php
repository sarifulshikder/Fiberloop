<?php

namespace App\Events\Billing;

use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a payment is received and recorded.
 */
class PaymentReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The payment instance.
     */
    public Payment $payment;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }
}
