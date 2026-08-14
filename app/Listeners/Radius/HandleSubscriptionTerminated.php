<?php

namespace App\Listeners\Radius;

use App\Events\Billing\SubscriptionTerminated;
use App\Models\RadiusCustomer;
use App\Services\Network\SubscriberProvisioningService;
use App\Services\Radius\RadiusCoaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleSubscriptionTerminated implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected SubscriberProvisioningService $provisioningService,
        protected RadiusCoaService $coaService
    ) {
    }

    /**
     * Handle the SubscriptionTerminated event.
     */
    public function handle(SubscriptionTerminated $event): void
    {
        $customer = $event->customer;
        Log::info("Handling subscription termination for customer #{$customer->id}", ['reason' => $event->reason]);

        $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)->first();
        if ($radiusCustomer && $radiusCustomer->radius_username) {
            $this->coaService->disconnectUser($radiusCustomer->radius_username);
        }

        $this->provisioningService->terminate($customer);
    }
}
