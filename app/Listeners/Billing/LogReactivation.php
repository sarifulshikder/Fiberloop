<?php

namespace App\Listeners\Billing;

use App\Events\Billing\SubscriptionReactivated;
use Illuminate\Support\Facades\Log;

/**
 * Log subscription reactivation events.
 * This is a stub listener that Phase 7 (FreeRADIUS) will extend to re-enable network access.
 */
class LogReactivation
{
    /**
     * Handle the event.
     */
    public function handle(SubscriptionReactivated $event): void
    {
        Log::info("Subscription reactivated", [
            'customer_id' => $event->customer->id,
            'payment_id' => $event->payment?->id,
            'payment_amount' => $event->payment?->amount,
            'reason' => $event->reason,
        ]);
    }
}
