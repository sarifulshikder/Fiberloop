<?php

namespace App\Services\Radius;

use App\Models\Customer;
use App\Models\RadCheck;
use App\Models\RadiusCustomer;
use App\Models\RadReply;
use App\Models\Subscription;
use Illuminate\Support\Str;

class RadiusProvisioningService
{
    /**
     * Create or update the central RadiusCustomer mapping record.
     * Kept separate from the RADIUS-table writes so the MikroTik API
     * provisioning path can reuse it without writing radcheck/radreply.
     */
    public function syncRadiusCustomer(Customer $customer, ?Subscription $subscription = null, array $attributes = []): RadiusCustomer
    {
        $subscription = $subscription ?? $customer->getActiveSubscription();
        $package = $subscription?->package;

        $existing = RadiusCustomer::where('customer_id', $customer->id)->first();

        $username = $attributes['radius_username']
            ?? $existing?->radius_username
            ?? $customer->radius_username
            ?? $customer->phone
            ?? 'user_' . $customer->id;

        $password = $attributes['radius_password']
            ?? $customer->radius_password
            ?? $existing?->radius_password
            ?? Str::random(10);

        $connectionType = $attributes['connection_type'] ?? $customer->connection_type?->value ?? 'pppoe';

        return RadiusCustomer::updateOrCreate(
            [
                'customer_id' => $customer->id,
            ],
            [
                'tenant_id' => $customer->tenant_id,
                'subscription_id' => $subscription?->id,
                'radius_username' => $username,
                'radius_password' => $password,
                'connection_type' => $connectionType,
                'max_download_speed' => $package?->download_speed,
                'max_upload_speed' => $package?->upload_speed,
                'framed_ip_address' => $attributes['framed_ip_address'] ?? null,
                'is_active' => true,
            ]
        );
    }

    /**
     * Provision or sync a customer's RADIUS user credentials and network attributes.
     */
    public function provisionUser(Customer $customer, ?Subscription $subscription = null, array $attributes = []): RadiusCustomer
    {
        $radiusCustomer = $this->syncRadiusCustomer($customer, $subscription, $attributes);

        $username = $radiusCustomer->radius_username;
        $password = $radiusCustomer->radius_password;
        $package = $subscription?->package ?? $customer->getActiveSubscription()?->package;
        $connectionType = $radiusCustomer->connection_type;
        RadCheck::updateOrCreate(
            [
                'username' => $username,
                'attribute' => 'Cleartext-Password',
            ],
            [
                'op' => ':=',
                'value' => $password,
            ]
        );

        // Remove any Reject rule if previously suspended
        RadCheck::where('username', $username)
            ->where('attribute', 'Auth-Type')
            ->delete();

        // 2. FreeRADIUS radreply entries (Bandwidth & Protocol)
        if ($package && ($package->download_speed || $package->upload_speed)) {
            $rateLimit = $this->formatRateLimit(
                $package->upload_speed ?? $package->download_speed,
                $package->download_speed
            );

            RadReply::updateOrCreate(
                [
                    'username' => $username,
                    'attribute' => 'Mikrotik-Rate-Limit',
                ],
                [
                    'op' => '=',
                    'value' => $rateLimit,
                ]
            );
        }

        if ($connectionType === 'pppoe') {
            RadReply::updateOrCreate(
                [
                    'username' => $username,
                    'attribute' => 'Framed-Protocol',
                ],
                [
                    'op' => '=',
                    'value' => 'PPP',
                ]
            );
        }

        if (!empty($attributes['framed_ip_address'])) {
            RadReply::updateOrCreate(
                [
                    'username' => $username,
                    'attribute' => 'Framed-IP-Address',
                ],
                [
                    'op' => '=',
                    'value' => $attributes['framed_ip_address'],
                ]
            );
        }

        return $radiusCustomer;
    }

    /**
     * Suspend RADIUS authentication for a customer (reject authentication).
     */
    public function suspendUser(Customer $customer, string $reason = 'Suspended'): void
    {
        $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)->first();

        if (!$radiusCustomer) {
            return;
        }

        $username = $radiusCustomer->radius_username;

        // Set Auth-Type := Reject in radcheck to block logins
        RadCheck::updateOrCreate(
            [
                'username' => $username,
                'attribute' => 'Auth-Type',
            ],
            [
                'op' => ':=',
                'value' => 'Reject',
            ]
        );

        $radiusCustomer->update([
            'is_active' => false,
        ]);
    }

    /**
     * Reactivate RADIUS authentication for a customer.
     */
    public function reactivateUser(Customer $customer): void
    {
        $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)->first();

        if (!$radiusCustomer) {
            return;
        }

        $username = $radiusCustomer->radius_username;

        // Remove Reject rule from radcheck
        RadCheck::where('username', $username)
            ->where('attribute', 'Auth-Type')
            ->delete();

        // Re-ensure Cleartext-Password exists
        RadCheck::updateOrCreate(
            [
                'username' => $username,
                'attribute' => 'Cleartext-Password',
            ],
            [
                'op' => ':=',
                'value' => $radiusCustomer->radius_password,
            ]
        );

        $radiusCustomer->update([
            'is_active' => true,
        ]);
    }

    /**
     * Terminate RADIUS user completely (remove radcheck and radreply entries).
     */
    public function terminateUser(Customer $customer): void
    {
        $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)->first();

        if (!$radiusCustomer) {
            return;
        }

        $username = $radiusCustomer->radius_username;

        $this->removeRadiusCredentials($username);

        $radiusCustomer->update([
            'is_active' => false,
        ]);
    }

    /**
     * Remove all radcheck/radreply rows for a username.
     */
    public function removeRadiusCredentials(string $username): void
    {
        RadCheck::where('username', $username)->delete();
        RadReply::where('username', $username)->delete();
    }

    /**
     * Update bandwidth profile for dynamic speed changes / FUP throttling.
     */
    public function updateBandwidthProfile(Customer $customer, int $downloadSpeedMbps, int $uploadSpeedMbps, ?string $customRateLimit = null): void
    {
        $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)->first();

        if (!$radiusCustomer) {
            return;
        }

        $username = $radiusCustomer->radius_username;
        $rateLimit = $customRateLimit ?? $this->formatRateLimit($uploadSpeedMbps, $downloadSpeedMbps);

        RadReply::updateOrCreate(
            [
                'username' => $username,
                'attribute' => 'Mikrotik-Rate-Limit',
            ],
            [
                'op' => '=',
                'value' => $rateLimit,
            ]
        );

        $radiusCustomer->update([
            'max_download_speed' => $downloadSpeedMbps,
            'max_upload_speed' => $uploadSpeedMbps,
        ]);
    }

    /**
     * Helper to format speeds in MikroTik rx-rate/tx-rate format (Upload/Download).
     */
    public function formatRateLimit(int $uploadSpeed, int $downloadSpeed): string
    {
        $rx = $uploadSpeed >= 1000 ? ($uploadSpeed / 1000).'M' : $uploadSpeed.'M';
        $tx = $downloadSpeed >= 1000 ? ($downloadSpeed / 1000).'M' : $downloadSpeed.'M';

        return "{$rx}/{$tx}";
    }

    /**
     * Flip the central mapping record to active without touching RADIUS tables.
     * Used by the MikroTik API provisioning path.
     */
    public function markActive(Customer $customer): void
    {
        RadiusCustomer::where('customer_id', $customer->id)
            ->update(['is_active' => true]);
    }

    /**
     * Flip the central mapping record to inactive without touching RADIUS tables.
     * Used by the MikroTik API provisioning path.
     */
    public function markInactive(Customer $customer): void
    {
        RadiusCustomer::where('customer_id', $customer->id)
            ->update(['is_active' => false]);
    }
}
