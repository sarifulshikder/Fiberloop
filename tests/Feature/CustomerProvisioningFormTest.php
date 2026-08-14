<?php

use App\Enums\DeviceVendor;
use App\Enums\ProvisioningMethod;
use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Models\Customer;
use App\Models\NetworkDevice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    filament()->setCurrentPanel(filament()->getPanel('admin'));
    $this->user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($this->user);
});

it('creates an active subscription and provisions radius user when a package is assigned', function () {
    $package = Package::factory()->create(['download_speed' => 20, 'upload_speed' => 10]);

    Livewire::test(CreateCustomer::class)
        ->fillForm([
            'first_name' => 'Provis',
            'last_name' => 'Test',
            'phone' => '01720000001',
            'password' => 'secret123',
            'service_address' => 'House 1',
            'provisioning_method' => ProvisioningMethod::RADIUS->value,
            'package_id' => $package->id,
        ])
        ->call('create');

    $customer = Customer::where('phone', '01720000001')->first();

    expect($customer)->not->toBeNull();

    $subscription = Subscription::where('customer_id', $customer->id)->first();
    expect($subscription)->not->toBeNull()
        ->and($subscription->status->value)->toBe('active')
        ->and($subscription->package_id)->toBe($package->id);

    $this->assertDatabaseHas('radcheck', [
        'username' => '01720000001',
        'attribute' => 'Cleartext-Password',
    ], 'radius');
});

it('shows the router selector only for mikrotik api provisioning', function () {
    $router = NetworkDevice::factory()->create([
        'name' => 'Core Router',
        'ip_address' => '10.5.50.1',
        'vendor' => DeviceVendor::MIKROTIK,
    ]);

    Livewire::test(CreateCustomer::class)
        ->fillForm([
            'first_name' => 'Method',
            'last_name' => 'Test',
            'phone' => '01720000002',
            'password' => 'secret123',
            'service_address' => 'House 2',
        ])
        ->assertFormFieldIsHidden('network_device_id')
        ->fillForm(['provisioning_method' => ProvisioningMethod::API->value])
        ->assertFormFieldIsVisible('network_device_id')
        ->assertFormFieldExists('network_device_id', function ($field) use ($router) {
            expect($field->getOptions())
                ->toHaveKey($router->id)
                ->and($field->getOptions()[$router->id])->toBe('Core Router (10.5.50.1)');

            return true;
        });

    expect($router)->not->toBeNull();
});

it('does not crash when provisioning an api customer without a router', function () {
    $package = Package::factory()->create();

    Livewire::test(CreateCustomer::class)
        ->fillForm([
            'first_name' => 'Api',
            'last_name' => 'Test',
            'phone' => '01720000003',
            'password' => 'secret123',
            'service_address' => 'House 3',
            'provisioning_method' => ProvisioningMethod::API->value,
            'package_id' => $package->id,
        ])
        ->call('create');

    $customer = Customer::where('phone', '01720000003')->first();

    expect($customer)->not->toBeNull();
    $this->assertDatabaseMissing('radcheck', ['username' => '01720000003'], 'radius');
});
