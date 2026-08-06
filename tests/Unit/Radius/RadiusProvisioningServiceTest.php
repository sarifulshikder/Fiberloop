<?php

namespace Tests\Unit\Radius;

use App\Models\Customer;
use App\Models\Package;
use App\Models\RadCheck;
use App\Models\RadReply;
use App\Models\RadiusCustomer;
use App\Models\Subscription;
use App\Services\Radius\RadiusProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RadiusProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private RadiusProvisioningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RadiusProvisioningService();
        \App\Models\User::factory()->create(['id' => 1]);
    }

    public function test_provision_user_creates_radcheck_and_radreply(): void
    {
        $package = Package::factory()->create([
            'download_speed' => 20,
            'upload_speed' => 10,
        ]);

        $customer = Customer::factory()->create([
            'phone' => '01700000001',
            'connection_type' => 'pppoe',
        ]);

        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
        ]);

        $radiusCustomer = $this->service->provisionUser($customer, $subscription, [
            'radius_password' => 'secret123',
        ]);

        $this->assertEquals('01700000001', $radiusCustomer->radius_username);
        $this->assertTrue($radiusCustomer->is_active);

        $this->assertDatabaseHas('radcheck', [
            'username' => '01700000001',
            'attribute' => 'Cleartext-Password',
            'value' => 'secret123',
        ], 'radius');

        $this->assertDatabaseHas('radreply', [
            'username' => '01700000001',
            'attribute' => 'Mikrotik-Rate-Limit',
            'value' => '10M/20M',
        ], 'radius');
    }

    public function test_suspend_user_sets_auth_type_reject(): void
    {
        $customer = Customer::factory()->create(['phone' => '01700000002']);
        $this->service->provisionUser($customer, null, ['radius_password' => 'pass123']);

        $this->service->suspendUser($customer);

        $this->assertDatabaseHas('radcheck', [
            'username' => '01700000002',
            'attribute' => 'Auth-Type',
            'value' => 'Reject',
        ], 'radius');

        $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)->first();
        $this->assertFalse($radiusCustomer->is_active);
    }

    public function test_reactivate_user_removes_auth_type_reject(): void
    {
        $customer = Customer::factory()->create(['phone' => '01700000003']);
        $this->service->provisionUser($customer, null, ['radius_password' => 'pass123']);
        $this->service->suspendUser($customer);

        $this->service->reactivateUser($customer);

        $this->assertDatabaseMissing('radcheck', [
            'username' => '01700000003',
            'attribute' => 'Auth-Type',
            'value' => 'Reject',
        ], 'radius');

        $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)->first();
        $this->assertTrue($radiusCustomer->is_active);
    }

    public function test_terminate_user_deletes_radcheck_and_radreply(): void
    {
        $customer = Customer::factory()->create(['phone' => '01700000004']);
        $this->service->provisionUser($customer, null, ['radius_password' => 'pass123']);

        $this->service->terminateUser($customer);

        $this->assertDatabaseMissing('radcheck', ['username' => '01700000004'], 'radius');
        $this->assertDatabaseMissing('radreply', ['username' => '01700000004'], 'radius');
    }

    public function test_update_bandwidth_profile(): void
    {
        $customer = Customer::factory()->create(['phone' => '01700000005']);
        $this->service->provisionUser($customer, null, ['radius_password' => 'pass123']);

        $this->service->updateBandwidthProfile($customer, 5, 2);

        $this->assertDatabaseHas('radreply', [
            'username' => '01700000005',
            'attribute' => 'Mikrotik-Rate-Limit',
            'value' => '2M/5M',
        ], 'radius');
    }
}
