<?php

namespace App\Listeners\Radius;

use App\Events\Billing\SubscriptionReactivated;
use App\Services\Network\SubscriberProvisioningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleSubscriptionReactivated implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected SubscriberProvisioningService $provisioningService
    ) {
    }

    /**
     * Handle the SubscriptionReactivated event.
     */
    public function handle(SubscriptionReactivated $event): void
    {
        $customer = $event->customer;
        Log::info("Handling subscription reactivation for customer #{$customer->id}", ['reason' => $event->reason]);

        // Restore provisioning state (RADIUS auth, or re-enable the MikroTik PPP secret).
        $this->provisioningService->reactivate($customer);
    }
}
