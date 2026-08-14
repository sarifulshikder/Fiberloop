<?php

namespace App\Services\Network;

use App\Enums\ProvisioningMethod;
use App\Models\Customer;
use App\Models\NetworkDevice;
use App\Models\RadiusCustomer;
use App\Models\Subscription;
use App\Services\Radius\RadiusProvisioningService;
use Closure;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Routes subscriber provisioning (provision/suspend/reactivate/terminate)
 * to either FreeRADIUS or the MikroTik router API, based on the customer's
 * chosen provisioning_method and assigned router.
 */
class SubscriberProvisioningService
{
    protected Closure $mikrotikFactory;

    public function __construct(
        protected RadiusProvisioningService $radius,
        ?Closure $mikrotikFactory = null,
    ) {
        $this->mikrotikFactory = $mikrotikFactory ?? fn (NetworkDevice $device) => new MikroTikService($device);
    }

    public function provision(Customer $customer, ?Subscription $subscription = null, array $attributes = []): RadiusCustomer
    {
        if (!$this->usesApi($customer)) {
            $radiusCustomer = $this->radius->provisionUser($customer, $subscription, $attributes);

            // If the customer previously used MikroTik API provisioning,
            // remove the old PPP secret so a single credential path exists.
            $this->removeStalePppSecret($customer, $radiusCustomer->radius_username);

            return $radiusCustomer;
        }

        $radiusCustomer = $this->radius->syncRadiusCustomer($customer, $subscription, $attributes);

        $device = $customer->networkDevice;
        if (!$device) {
            Log::error("API provisioning skipped: no router assigned to customer #{$customer->id}");

            throw new RuntimeException(
                "Customer #{$customer->id} is set to MikroTik API provisioning but has no router (network device) assigned."
            );
        }

        $mikrotik = ($this->mikrotikFactory)($device);
        $package = $subscription?->package ?? $customer->getActiveSubscription()?->package;

        $profile = null;
        if ($package && ($package->download_speed || $package->upload_speed)) {
            $profile = $mikrotik->ensurePppProfile(
                (int) $package->download_speed,
                (int) $package->upload_speed
            );
        }

        $created = $mikrotik->setPppSecret(
            $radiusCustomer->radius_username,
            $radiusCustomer->radius_password,
            $profile,
            $radiusCustomer->framed_ip_address,
            !$radiusCustomer->is_active
        );

        if (!$created) {
            Log::error("Failed to provision MikroTik PPP secret for customer #{$customer->id}", [
                'device' => $device->id,
                'username' => $radiusCustomer->radius_username,
            ]);
        }

        // Clean any stale RADIUS entries in case the customer previously
        // used RADIUS provisioning — a single credential path must exist.
        $this->radius->removeRadiusCredentials($radiusCustomer->radius_username);

        return $radiusCustomer;
    }

    public function suspend(Customer $customer, string $reason = 'Suspended'): void
    {
        if (!$this->usesApi($customer)) {
            $this->radius->suspendUser($customer, $reason);

            return;
        }

        $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)->first();
        $device = $customer->networkDevice;

        if ($device && $radiusCustomer) {
            $mikrotik = ($this->mikrotikFactory)($device);
            $mikrotik->setPppSecretEnabled($radiusCustomer->radius_username, false);
            $mikrotik->disconnectPppoeSession($radiusCustomer->radius_username);
        }

        $this->radius->markInactive($customer);
    }

    public function reactivate(Customer $customer): void
    {
        if (!$this->usesApi($customer)) {
            $this->radius->reactivateUser($customer);

            return;
        }

        $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)->first();
        $device = $customer->networkDevice;

        if ($device && $radiusCustomer) {
            ($this->mikrotikFactory)($device)->setPppSecretEnabled($radiusCustomer->radius_username, true);
        }

        $this->radius->markActive($customer);
    }

    public function terminate(Customer $customer): void
    {
        if (!$this->usesApi($customer)) {
            $this->radius->terminateUser($customer);

            return;
        }

        $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)->first();
        $device = $customer->networkDevice;

        if ($device && $radiusCustomer) {
            ($this->mikrotikFactory)($device)->removePppSecret($radiusCustomer->radius_username);
        }

        // Also clean any stale radcheck/radreply in case the customer
        // previously used RADIUS provisioning.
        $this->radius->terminateUser($customer);
    }

    protected function usesApi(Customer $customer): bool
    {
        return ($customer->provisioning_method ?? ProvisioningMethod::RADIUS) === ProvisioningMethod::API;
    }

    /**
     * Remove the PPP secret a customer left behind on a router after
     * switching from MikroTik API back to RADIUS provisioning.
     */
    protected function removeStalePppSecret(Customer $customer, string $username): void
    {
        // Check the FK attribute first — accessing the relation would cache a
        // null result on the model, surviving a later update() on the FK.
        if (!$customer->network_device_id) {
            return;
        }

        $device = $customer->networkDevice;

        if (!$device) {
            return;
        }

        try {
            ($this->mikrotikFactory)($device)->removePppSecret($username);
        } catch (\Throwable $e) {
            Log::warning("Could not remove stale PPP secret for customer #{$customer->id} on device #{$device->id}", [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
