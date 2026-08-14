<?php

namespace Tests\Unit\Network;

use App\Enums\DeviceVendor;
use App\Enums\NetworkManagementProtocol;
use App\Models\NetworkDevice;
use App\Models\Olt;
use App\Models\OltPort;
use App\Services\Network\OltDrivers\VsolCliDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FixtureVsolCliDriver extends VsolCliDriver
{
    public array $responses = [];

    public string $ponInfoResponse = '';

    public string $gigabitInfoResponse = '';

    protected function runCommand(string $command): string
    {
        if (array_key_exists($command, $this->responses)) {
            return $this->responses[$command];
        }

        if ($this->ponInfoResponse !== '' && str_contains($command, 'show pon info')) {
            return $this->ponInfoResponse;
        }

        if ($this->gigabitInfoResponse !== '' && str_contains($command, 'show interface gigabitethernet')) {
            return $this->gigabitInfoResponse;
        }

        return '';
    }

    public function command(string $key): string
    {
        return sprintf(config("olt.commands.vsol.{$key}"), '0/1');
    }
}

describe('VsolCliDriver', function () {
    beforeEach(function () {
        $this->tenant = \App\Models\Tenant::create(['name' => 'Test Tenant']);

        $this->device = NetworkDevice::factory()->create([
            'vendor' => DeviceVendor::VSOL,
            'management_protocol' => NetworkManagementProtocol::SSH,
            'ip_address' => '10.0.0.1',
            'username' => 'admin',
            'configuration' => ['telnet_port' => 223],
        ]);

        $this->olt = Olt::factory()->forNetworkDevice($this->device)->create([
            'tenant_id' => $this->tenant->id,
            'configuration' => ['pon_ports' => ['0/1', '0/2']],
        ]);

        $this->driver = new FixtureVsolCliDriver($this->olt);
    });

    it('polls pon ports over the cli and creates olt port records', function () {
        $this->driver->ponInfoResponse = <<<TXT

****************EPON0/1****************
 PON Link status     : enable
 PON Admin Status    : enable
 Encryption Mode     : disable
TXT;

        $result = $this->driver->pollPorts();

        expect($result['polled'])->toBe(2)
            ->and($result['created'])->toBe(2)
            ->and($result['updated'])->toBe(0)
            ->and($result['reachable'])->toBeTrue();

        $ports = OltPort::where('olt_id', $this->olt->id)->orderBy('if_index')->get();

        expect($ports)->toHaveCount(2)
            ->and($ports[0]->name)->toBe('EPON0/1')
            ->and($ports[0]->if_index)->toBe(1)
            ->and($ports[0]->is_pon)->toBeTrue()
            ->and($ports[0]->admin_status)->toBe(1)
            ->and($ports[0]->oper_status)->toBe(1)
            ->and($ports[1]->name)->toBe('EPON0/2')
            ->and($ports[1]->if_index)->toBe(2)
            ->and($ports[1]->admin_status)->toBe(1)
            ->and($ports[1]->oper_status)->toBe(1)
            ->and($ports[1]->last_polled_at)->not->toBeNull();
    });

    it('updates existing ports instead of duplicating', function () {
        OltPort::create([
            'olt_id' => $this->olt->id,
            'tenant_id' => $this->olt->tenant_id,
            'network_device_id' => $this->device->id,
            'if_index' => 1,
            'name' => 'EPON0/1',
            'type_label' => 'pon',
            'is_pon' => true,
        ]);

        $this->driver->ponInfoResponse = <<<TXT
 PON Link status     : disable
 PON Admin Status    : enable
TXT;

        $result = $this->driver->pollPorts();

        expect($result['created'])->toBe(1)
            ->and($result['updated'])->toBe(1)
            ->and(OltPort::where('olt_id', $this->olt->id)->count())->toBe(2)
            ->and(OltPort::where('olt_id', $this->olt->id)->where('if_index', 1)->first()->oper_status)->toBe(2);
    });

    it('polls gigabitethernet ports and classifies uplink/access/other', function () {
        $gigabit = static function (int $port, string $state, ?string $desc = null, string $hw = 'Gigabit Ethernet', int $speed = 1000): string {
            $descLine = $desc !== null ? "    Description: {$desc}\n" : '';

            return <<<TXT
Interface gigabitEthernet0/{$port}'s information.
    GigabitEthernet0/{$port} current state : {$state}
{$descLine}    Hardware Type is {$hw}, Hardware address is 0:0:0:0:0:0
    The Maximum Transmit Unit is 1500
    Current link speed: {$speed}Mbps,  Current link mode: full-duplex
TXT;
        };

        foreach (range(1, 8) as $i) {
            $this->driver->responses['show interface gigabitethernet 0/' . $i] = match ($i) {
                1, 2 => $gigabit($i, $i === 1 ? 'Up' : 'Down', 'UpR-Link', '10 Gigabit Ethernet', 10000),
                3, 4 => $gigabit($i, 'Down', null, '10 Gigabit Ethernet', 10000),
                5, 6, 7 => $gigabit($i, 'Up', 'Mikrotik', 'Gigabit Ethernet', 1000),
                default => $gigabit($i, 'Down', null, 'Gigabit Ethernet', 1000),
            };
        }

        $this->driver->ponInfoResponse = <<<TXT
 PON Link status     : enable
 PON Admin Status    : enable
TXT;

        $result = $this->driver->pollPorts();

        expect($result['polled'])->toBe(10)
            ->and($result['created'])->toBe(10)
            ->and($result['updated'])->toBe(0);

        $ge1 = OltPort::where('olt_id', $this->olt->id)->where('if_index', 101)->first();
        expect($ge1)->not->toBeNull()
            ->and($ge1->name)->toBe('GE0/1')
            ->and($ge1->type_label)->toBe('uplink')
            ->and($ge1->is_uplink)->toBeTrue()
            ->and($ge1->is_pon)->toBeFalse()
            ->and($ge1->oper_status)->toBe(1)
            ->and($ge1->alias)->toBe('UpR-Link')
            ->and($ge1->high_speed)->toBe(10000)
            ->and($ge1->speed)->toBe(10_000_000_000)
            ->and($ge1->mtu)->toBe(1500);

        $ge2 = OltPort::where('olt_id', $this->olt->id)->where('if_index', 102)->first();
        expect($ge2->type_label)->toBe('uplink')
            ->and($ge2->is_uplink)->toBeTrue()
            ->and($ge2->oper_status)->toBe(2);

        $ge3 = OltPort::where('olt_id', $this->olt->id)->where('if_index', 103)->first();
        expect($ge3->type_label)->toBe('other')
            ->and($ge3->is_uplink)->toBeFalse()
            ->and($ge3->oper_status)->toBe(2);

        $ge5 = OltPort::where('olt_id', $this->olt->id)->where('if_index', 105)->first();
        expect($ge5->type_label)->toBe('access')
            ->and($ge5->is_uplink)->toBeFalse()
            ->and($ge5->oper_status)->toBe(1)
            ->and($ge5->alias)->toBe('Mikrotik');
    });

    it('keeps existing gigabitethernet ports when nothing changes', function () {
        OltPort::create([
            'olt_id' => $this->olt->id,
            'tenant_id' => $this->olt->tenant_id,
            'network_device_id' => $this->device->id,
            'if_index' => 101,
            'name' => 'GE0/1',
            'type_label' => 'uplink',
            'is_uplink' => true,
        ]);

        $this->driver->gigabitInfoResponse = <<<TXT
Interface gigabitEthernet0/1's information.
    GigabitEthernet0/1 current state : Up
    Description: UpR-Link
    Hardware Type is 10 Gigabit Ethernet, Hardware address is 0:0:0:0:0:0
    Current link speed: 10000Mbps,  Current link mode: full-duplex
TXT;

        $result = $this->driver->pollPorts();

        expect($result['created'])->toBe(7)
            ->and($result['updated'])->toBe(1)
            ->and(OltPort::where('olt_id', $this->olt->id)->where('if_index', 101)->first()->name)->toBe('GE0/1');
    });
});
