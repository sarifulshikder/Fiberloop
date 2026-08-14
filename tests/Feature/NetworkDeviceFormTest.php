<?php

use App\Enums\DeviceVendor;
use App\Enums\NetworkManagementProtocol;
use App\Filament\Resources\NetworkDevices\Pages\CreateNetworkDevice;
use App\Filament\Resources\NetworkDevices\Pages\EditNetworkDevice;
use App\Models\NetworkDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    filament()->setCurrentPanel(filament()->getPanel('admin'));
});

it('shows the telnet cli port field for ssh-managed devices', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    $device = NetworkDevice::factory()->create([
        'management_protocol' => NetworkManagementProtocol::SSH,
        'telnet_port' => 223,
    ]);

    Livewire::test(EditNetworkDevice::class, ['record' => $device->getKey()])
        ->assertFormFieldExists('telnet_port')
        ->assertFormFieldIsVisible('telnet_port');
});

it('prefills the telnet cli port from the device', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    $device = NetworkDevice::factory()->create([
        'management_protocol' => NetworkManagementProtocol::SSH,
        'telnet_port' => 223,
    ]);

    Livewire::test(EditNetworkDevice::class, ['record' => $device->getKey()])
        ->assertFormSet([
            'telnet_port' => 223,
        ]);
});

it('keeps the telnet cli port editable for snmp-only devices too', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    $device = NetworkDevice::factory()->create([
        'management_protocol' => NetworkManagementProtocol::SNMP,
    ]);

    Livewire::test(EditNetworkDevice::class, ['record' => $device->getKey()])
        ->assertFormFieldExists('telnet_port')
        ->assertFormFieldIsVisible('telnet_port')
        ->assertFormFieldExists('snmp_port')
        ->assertFormFieldIsVisible('snmp_port');
});

it('exposes api, ssh, telnet and snmp ports in the network connectivity section', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    $device = NetworkDevice::factory()->create([
        'management_protocol' => NetworkManagementProtocol::SSH,
        'port' => 8728,
        'ssh_port' => 22,
        'telnet_port' => 223,
        'snmp_port' => 161,
    ]);

    Livewire::test(EditNetworkDevice::class, ['record' => $device->getKey()])
        ->assertFormFieldExists('ip_address')
        ->assertFormFieldExists('hostname')
        ->assertFormFieldExists('username')
        ->assertFormFieldExists('password')
        ->assertFormFieldExists('port')
        ->assertFormFieldExists('ssh_port')
        ->assertFormFieldExists('telnet_port')
        ->assertFormFieldExists('snmp_port')
        ->assertFormSet([
            'port' => 8728,
            'ssh_port' => 22,
            'telnet_port' => 223,
            'snmp_port' => 161,
        ]);
});

it('creates a device with all port fields left blank', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    Livewire::test(CreateNetworkDevice::class)
        ->fillForm([
            'name' => 'Blank Port Router',
            'vendor' => DeviceVendor::MIKROTIK->value,
            'model' => 'CCR2004',
            'serial_number' => 'SN-BLANK-0001',
            'ip_address' => '10.99.99.99',
            'management_protocol' => NetworkManagementProtocol::SNMP->value,
            'snmp_version' => 'v2c',
            'port' => null,
            'ssh_port' => null,
            'telnet_port' => null,
            'snmp_port' => null,
        ])
        ->call('create');

    $device = NetworkDevice::where('serial_number', 'SN-BLANK-0001')->first();

    expect($device)->not->toBeNull()
        ->and($device->port)->toBeNull()
        ->and($device->ssh_port)->toBeNull()
        ->and($device->telnet_port)->toBeNull()
        ->and($device->snmp_port)->toBeNull();
});

it('offers the routeros api protocol and hides snmp fields for api devices', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    Livewire::test(CreateNetworkDevice::class)
        ->fillForm([
            'management_protocol' => NetworkManagementProtocol::API->value,
        ])
        ->assertFormSet(['management_protocol' => NetworkManagementProtocol::API->value])
        ->assertFormFieldExists('port')
        ->assertFormFieldIsVisible('port')
        ->assertFormFieldIsHidden('snmp_community')
        ->assertFormFieldIsHidden('snmp_version')
        ->assertFormFieldIsHidden('snmp_port');
});

it('creates a mikrotik device managed over the routeros api', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    Livewire::test(CreateNetworkDevice::class)
        ->fillForm([
            'name' => 'API Router',
            'vendor' => DeviceVendor::MIKROTIK->value,
            'model' => 'CCR2004',
            'serial_number' => 'SN-API-0001',
            'ip_address' => '10.99.99.98',
            'username' => 'admin',
            'password' => 'secret',
            'management_protocol' => NetworkManagementProtocol::API->value,
            'port' => 8728,
        ])
        ->call('create');

    $device = NetworkDevice::where('serial_number', 'SN-API-0001')->first();

    expect($device)->not->toBeNull()
        ->and($device->management_protocol)->toBe(NetworkManagementProtocol::API)
        ->and($device->port)->toBe(8728);
});
