<?php

namespace App\Events\Billing;

use App\Models\Customer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a customer's subscription or account is terminated.
 * Phase 7 (FreeRADIUS) listens to this event to remove network access credentials.
 */
class SubscriptionTerminated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Customer $customer;
    public string $reason;

    public function __construct(Customer $customer, string $reason = 'Account terminated')
    {
        $this->customer = $customer;
        $this->reason = $reason;
    }
}
