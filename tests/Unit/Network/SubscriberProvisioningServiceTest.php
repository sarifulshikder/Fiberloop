<?php

namespace Tests\Unit\Network;

use App\Enums\ProvisioningMethod;
use App\Models\Customer;
use App\Models\NetworkDevice;
use App\Models\Package;
use App\Models\Subscription;
use App\Services\Network\MikroTikService;
use App\Services\Network\SubscriberProvisioningService;
use App\Services\Radius\RadiusProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SubscriberProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function orchestrator(?MikroTikService $mikrotik = null): SubscriberProvisioningService
    {
        return new SubscriberProvisioningService(
            new RadiusProvisioningService(),
            $mikrotik ? fn () => $mikrotik : null,
        );
    }

    private function mikrotikDevice(): NetworkDevice
    {
        return NetworkDevice::factory()->create(['vendor' => 'mikrotik']);
    }

    public function test_radius_provision_creates_radcheck_and_radreply(): void
    {
        $package = Package::factory()->create(['download_speed' => 20, 'upload_speed' => 10]);
        $customer = Customer::factory()->create([
            'phone' => '01710000001',
            'provisioning_method' => ProvisioningMethod::RADIUS->value,
        ]);
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
        ]);

        $this->orchestrator()->provision($customer, $subscription, ['radius_password' => 'secret123']);

        $this->assertDatabaseHas('radcheck', [
            'username' => '01710000001',
            'attribute' => 'Cleartext-Password',
            'value' => 'secret123',
        ], 'radius');
        $this->assertDatabaseHas('radreply', [
            'username' => '01710000001',
            'attribute' => 'Mikrotik-Rate-Limit',
            'value' => '10M/20M',
        ], 'radius');
    }

    public function test_radius_suspend_reactivate_terminate(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '01710000002',
            'provisioning_method' => ProvisioningMethod::RADIUS->value,
        ]);
        $service = $this->orchestrator();

        $service->provision($customer, null, ['radius_password' => 'secret123']);
        $service->suspend($customer);

        $this->assertDatabaseHas('radcheck', [
            'username' => '01710000002',
            'attribute' => 'Auth-Type',
            'value' => 'Reject',
        ], 'radius');

        $service->reactivate($customer);

        $this->assertDatabaseMissing('radcheck', [
            'username' => '01710000002',
            'attribute' => 'Auth-Type',
        ], 'radius');

        $service->terminate($customer);

        $this->assertDatabaseMissing('radcheck', ['username' => '01710000002'], 'radius');
        $this->assertDatabaseMissing('radreply', ['username' => '01710000002'], 'radius');
    }

    public function test_api_provision_creates_ppp_secret_and_central_record_only(): void
    {
        $package = Package::factory()->create(['download_speed' => 20, 'upload_speed' => 10]);
        $device = $this->mikrotikDevice();
        $customer = Customer::factory()->create([
            'phone' => '01710000003',
            'provisioning_method' => ProvisioningMethod::API->value,
            'network_device_id' => $device->id,
        ]);
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
        ]);

        $mikrotik = Mockery::mock(MikroTikService::class);
        $mikrotik->shouldReceive('ensurePppProfile')->once()->andReturn('fiberloop-20M-10M');
        $mikrotik->shouldReceive('setPppSecret')
            ->once()
            ->with('01710000003', 'secret123', 'fiberloop-20M-10M', null, false)
            ->andReturn(true);

        $this->orchestrator($mikrotik)->provision($customer, $subscription, ['radius_password' => 'secret123']);

        $this->assertDatabaseHas('radius_customers', [
            'customer_id' => $customer->id,
            'radius_username' => '01710000003',
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('radcheck', ['username' => '01710000003'], 'radius');
    }

    public function test_api_suspend_disables_secret(): void
    {
        $device = $this->mikrotikDevice();
        $customer = Customer::factory()->create([
            'phone' => '01710000004',
            'provisioning_method' => ProvisioningMethod::API->value,
            'network_device_id' => $device->id,
        ]);

        $mikrotik = Mockery::mock(MikroTikService::class);
        $mikrotik->shouldReceive('ensurePppProfile')->andReturnNull();
        $mikrotik->shouldReceive('setPppSecret')->once()->andReturn(true);
        $mikrotik->shouldReceive('setPppSecretEnabled')->once()->with('01710000004', false)->andReturn(true);
        $mikrotik->shouldReceive('disconnectPppoeSession')->once()->with('01710000004')->andReturn(true);

        $service = $this->orchestrator($mikrotik);
        $service->provision($customer, null, ['radius_password' => 'secret123']);
        $service->suspend($customer);

        $this->assertDatabaseHas('radius_customers', [
            'customer_id' => $customer->id,
            'is_active' => false,
        ]);
    }

    public function test_api_reactivate_enables_secret(): void
    {
        $device = $this->mikrotikDevice();
        $customer = Customer::factory()->create([
            'phone' => '01710000005',
            'provisioning_method' => ProvisioningMethod::API->value,
            'network_device_id' => $device->id,
        ]);

        $mikrotik = Mockery::mock(MikroTikService::class);
        $mikrotik->shouldReceive('ensurePppProfile')->andReturnNull();
        $mikrotik->shouldReceive('setPppSecret')->once()->andReturn(true);
        $mikrotik->shouldReceive('setPppSecretEnabled')->once()->with('01710000005', true)->andReturn(true);

        $service = $this->orchestrator($mikrotik);
        $service->provision($customer, null, ['radius_password' => 'secret123']);
        $service->reactivate($customer);

        $this->assertDatabaseHas('radius_customers', [
            'customer_id' => $customer->id,
            'is_active' => true,
        ]);
    }

    public function test_api_terminate_removes_secret(): void
    {
        $device = $this->mikrotikDevice();
        $customer = Customer::factory()->create([
            'phone' => '01710000006',
            'provisioning_method' => ProvisioningMethod::API->value,
            'network_device_id' => $device->id,
        ]);

        $mikrotik = Mockery::mock(MikroTikService::class);
        $mikrotik->shouldReceive('ensurePppProfile')->andReturnNull();
        $mikrotik->shouldReceive('setPppSecret')->once()->andReturn(true);
        $mikrotik->shouldReceive('removePppSecret')->once()->with('01710000006')->andReturn(true);

        $service = $this->orchestrator($mikrotik);
        $service->provision($customer, null, ['radius_password' => 'secret123']);
        $service->terminate($customer);

        $this->assertDatabaseHas('radius_customers', [
            'customer_id' => $customer->id,
            'is_active' => false,
        ]);
    }

    public function test_api_provision_removes_stale_radius_entries(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '01710000008',
            'provisioning_method' => ProvisioningMethod::RADIUS->value,
        ]);

        $service = $this->orchestrator();
        $service->provision($customer, null, ['radius_password' => 'secret123']);

        $this->assertDatabaseHas('radcheck', ['username' => '01710000008'], 'radius');

        // Admin switches the customer to MikroTik API provisioning.
        $device = $this->mikrotikDevice();
        $customer->update([
            'provisioning_method' => ProvisioningMethod::API->value,
            'network_device_id' => $device->id,
        ]);

        $mikrotik = Mockery::mock(MikroTikService::class);
        $mikrotik->shouldReceive('ensurePppProfile')->andReturnNull();
        $mikrotik->shouldReceive('setPppSecret')->once()->andReturn(true);

        $this->orchestrator($mikrotik)->provision($customer, null, ['radius_password' => 'secret123']);

        $this->assertDatabaseMissing('radcheck', ['username' => '01710000008'], 'radius');
        $this->assertDatabaseMissing('radreply', ['username' => '01710000008'], 'radius');
    }

    public function test_radius_provision_removes_stale_ppp_secret(): void
    {
        $device = $this->mikrotikDevice();
        $customer = Customer::factory()->create([
            'phone' => '01710000009',
            'provisioning_method' => ProvisioningMethod::RADIUS->value,
            // Router kept from a previous MikroTik API provisioning.
            'network_device_id' => $device->id,
        ]);

        $mikrotik = Mockery::mock(MikroTikService::class);
        $mikrotik->shouldReceive('removePppSecret')->once()->with('01710000009')->andReturn(true);

        $this->orchestrator($mikrotik)->provision($customer, null, ['radius_password' => 'secret123']);

        $this->assertDatabaseHas('radcheck', ['username' => '01710000009'], 'radius');
    }

    public function test_api_provision_without_router_throws(): void
    {
        $this->expectException(RuntimeException::class);

        $customer = Customer::factory()->create([
            'phone' => '01710000007',
            'provisioning_method' => ProvisioningMethod::API->value,
            'network_device_id' => null,
        ]);

        $this->orchestrator()->provision($customer, null, ['radius_password' => 'secret123']);
    }
}
