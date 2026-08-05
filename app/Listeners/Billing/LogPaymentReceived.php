<?php

namespace App\Listeners\Billing;

use App\Events\Billing\PaymentReceived;
use Illuminate\Support\Facades\Log;

/**
 * Log payment received events.
 */
class LogPaymentReceived
{
    /**
     * Handle the event.
     */
    public function handle(PaymentReceived $event): void
    {
        Log::info("Payment received", [
            'payment_id' => $event->payment->id,
            'invoice_id' => $event->payment->invoice_id,
            'customer_id' => $event->payment->customer_id,
            'amount' => $event->payment->amount,
            'method' => $event->payment->method,
            'gateway_reference' => $event->payment->gateway_reference,
        ]);
    }
}