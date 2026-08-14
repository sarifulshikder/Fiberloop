<?php

use App\Enums\NetworkManagementProtocol;
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
        'configuration' => ['telnet_port' => 223],
    ]);

    Livewire::test(EditNetworkDevice::class, ['record' => $device->getKey()])
        ->assertFormFieldExists('configuration.telnet_port')
        ->assertFormFieldIsVisible('configuration.telnet_port');
});

it('prefills the telnet cli port from the device configuration', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    $device = NetworkDevice::factory()->create([
        'management_protocol' => NetworkManagementProtocol::SSH,
        'configuration' => ['telnet_port' => 223],
    ]);

    Livewire::test(EditNetworkDevice::class, ['record' => $device->getKey()])
        ->assertFormSet([
            'configuration' => [
                'telnet_port' => 223,
            ],
        ]);
});

it('hides the telnet cli port field for snmp-only devices', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    $device = NetworkDevice::factory()->create([
        'management_protocol' => NetworkManagementProtocol::SNMP,
        'configuration' => null,
    ]);

    Livewire::test(EditNetworkDevice::class, ['record' => $device->getKey()])
        ->assertFormFieldExists('configuration.telnet_port')
        ->assertFormFieldIsHidden('configuration.telnet_port');
});
