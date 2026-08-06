<?php

namespace App\Listeners\Billing;

use App\Events\Billing\InvoiceGenerated;
use Illuminate\Support\Facades\Log;

/**
 * Log invoice generation events.
 */
class LogInvoiceGenerated
{
    /**
     * Handle the event.
     */
    public function handle(InvoiceGenerated $event): void
    {
        Log::info("Invoice generated", [
            'invoice_id' => $event->invoice->id,
            'invoice_number' => $event->invoice->invoice_number,
            'customer_id' => $event->invoice->customer_id,
            'subscription_id' => $event->invoice->subscription_id,
            'total_amount' => $event->invoice->total,
            'period_start' => $event->invoice->period_start,
            'period_end' => $event->invoice->period_end,
            'due_date' => $event->invoice->due_date,
        ]);
    }
}
