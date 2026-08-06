<?php

namespace App\Listeners\Reseller;

use App\Events\Billing\PaymentReceived;
use App\Services\Reseller\CommissionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listens to PaymentReceived and credits commission to the associated reseller.
 * Queued so it never blocks the payment flow.
 */
class CreditResellerCommissionOnPayment implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly CommissionService $commissionService)
    {
    }

    public function handle(PaymentReceived $event): void
    {
        try {
            $this->commissionService->creditCommission($event->payment);
        } catch (\Throwable $e) {
            Log::error('Failed to credit reseller commission', [
                'payment_id' => $event->payment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw so Laravel queues retry
        }
    }
}
