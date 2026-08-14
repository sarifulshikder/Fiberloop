<?php

namespace App\Listeners\Radius;

use App\Events\Billing\SubscriptionSuspended;
use App\Models\RadiusCustomer;
use App\Services\Network\SubscriberProvisioningService;
use App\Services\Radius\RadiusCoaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleSubscriptionSuspended implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected SubscriberProvisioningService $provisioningService,
        protected RadiusCoaService $coaService
    ) {
    }

    /**
     * Handle the SubscriptionSuspended event.
     */
    public function handle(SubscriptionSuspended $event): void
    {
        $customer = $event->customer;
        Log::info("Handling subscription suspension for customer #{$customer->id}", ['reason' => $event->reason]);

        // 1. Suspend provisioning state (RADIUS Auth-Type Reject, or disable the
        //    MikroTik PPP secret when the customer uses API provisioning).
        $this->provisioningService->suspend($customer, $event->reason);

        // 2. Send CoA / Disconnect to drop any active session on NAS
        $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)->first();
        if ($radiusCustomer && $radiusCustomer->radius_username) {
            $this->coaService->disconnectUser($radiusCustomer->radius_username);
        }
    }
}
