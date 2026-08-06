<?php

namespace App\Listeners\Radius;

use App\Events\Billing\SubscriptionReactivated;
use App\Services\Radius\RadiusProvisioningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleSubscriptionReactivated implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected RadiusProvisioningService $provisioningService
    ) {
    }

    /**
     * Handle the SubscriptionReactivated event.
     */
    public function handle(SubscriptionReactivated $event): void
    {
        $customer = $event->customer;
        Log::info("Handling RADIUS reactivation for customer #{$customer->id}", ['reason' => $event->reason]);

        // Restore RADIUS authentication state in DB
        $this->provisioningService->reactivateUser($customer);
    }
}
