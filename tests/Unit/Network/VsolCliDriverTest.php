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

    protected function runCommand(string $command): string
    {
        if ($this->ponInfoResponse !== '' && str_contains($command, 'show pon info')) {
            return $this->ponInfoResponse;
        }

        return $this->responses[$command] ?? '';
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
});
