<?php

namespace App\Events\Billing;

use App\Models\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an invoice is generated.
 */
class InvoiceGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The invoice instance.
     */
    public Invoice $invoice;

    /**
     * Create a new event instance.
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }
}
