<?php

namespace App\Listeners\Billing;

use App\Events\Billing\SubscriptionSuspended;
use Illuminate\Support\Facades\Log;

/**
 * Log subscription suspension events.
 * This is a stub listener that Phase 7 (FreeRADIUS) will extend to disable network access.
 */
class LogSuspension
{
    /**
     * Handle the event.
     */
    public function handle(SubscriptionSuspended $event): void
    {
        Log::info("Subscription suspended", [
            'customer_id' => $event->customer->id,
            'invoice_id' => $event->invoice->id,
            'invoice_number' => $event->invoice->invoice_number,
            'outstanding_amount' => $event->invoice->outstanding_amount,
            'reason' => $event->reason,
        ]);
    }
}
