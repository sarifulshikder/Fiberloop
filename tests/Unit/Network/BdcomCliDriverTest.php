<?php

namespace Tests\Unit\Network;

use App\Enums\DeviceVendor;
use App\Enums\NetworkManagementProtocol;
use App\Models\NetworkDevice;
use App\Models\Olt;
use App\Models\OltPort;
use App\Services\Network\OltDrivers\BdcomCliDriver;
use App\Services\Network\TelnetTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionProperty;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FixtureBdcomCliDriver extends BdcomCliDriver
{
    public array $responses = [];

    protected function runCommand(string $command): string
    {
        return $this->responses[$command] ?? '';
    }
}

describe('BdcomCliDriver', function () {
    beforeEach(function () {
        $this->tenant = \App\Models\Tenant::create(['name' => 'Test Tenant']);

        $this->device = NetworkDevice::factory()->create([
            'vendor' => DeviceVendor::BDCOM,
            'management_protocol' => NetworkManagementProtocol::SSH,
            'ip_address' => '10.0.0.9',
            'username' => 'Sariful',
            'telnet_port' => 225,
        ]);

        $this->olt = Olt::factory()->forNetworkDevice($this->device)->create([
            'tenant_id' => $this->tenant->id,
            'configuration' => [
                'pon_ports' => ['0/1', '0/2'],
                'uplink_ports' => ['0/1', '0/6'],
            ],
        ]);

        $this->driver = new FixtureBdcomCliDriver($this->olt);
    });

    it('builds a telnet transport on the configured port with the bdcom prompt', function () {
        $property = new ReflectionProperty($this->driver, 'transport');
        $transport = $property->getValue($this->driver);

        expect($transport)->toBeInstanceOf(TelnetTransport::class);
    });

    it('maps pon_ports to bare port numbers for epon0/%s commands', function () {
        $driver = new class ($this->olt) extends FixtureBdcomCliDriver {
            public function publicPonPortIdentifiers(): array
            {
                return $this->ponPortIdentifiers();
            }
        };

        expect($driver->publicPonPortIdentifiers())->toBe(['1', '2']);
    });

    it('polls pon ports over the cli and creates olt port records', function () {
        foreach (['1', '2'] as $port) {
            $this->driver->responses['show interface epon0/' . $port] = <<<TXT
EPON0/{$port} is up, line protocol is up
Description: Port_{$port}_Customer
Hardware is Giga-PON, address is 00:e0:50:48:5c:24
MTU 1500 bytes, BW 1000000 kbit, DLY 2000 usec
TXT;
        }

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
            ->and($ports[0]->alias)->toBe('Port_1_Customer')
            ->and($ports[1]->name)->toBe('EPON0/2')
            ->and($ports[1]->if_index)->toBe(2)
            ->and($ports[1]->last_polled_at)->not->toBeNull();
    });

    it('polls gigabitethernet ports and classifies uplinks from the config', function () {
        $this->driver->responses['show interface epon0/1'] = "EPON0/1 is up, line protocol is up\nMTU 1500 bytes\n";
        $this->driver->responses['show interface epon0/2'] = "EPON0/2 is down, line protocol is down\nMTU 1500 bytes\n";

        $gigabit = static function (int $port, string $state, ?string $desc = null): string {
            $descLine = $desc !== null ? "Description: {$desc}\n" : '';

            return "GigaEthernet0/{$port} is {$state}, line protocol is {$state}\n"
                . "{$descLine}Hardware is Giga-TX, address is 00:e0:50:48:5c:22\n"
                . "MTU 1500 bytes, BW 1000000 kbit, DLY 2000 usec\n"
                . "Auto-Duplex(Full), Auto-Speed(1000Mb/s)\n";
        };

        foreach (range(1, 6) as $i) {
            $this->driver->responses['show interface GigaEthernet0/' . $i] = $gigabit(
                $i,
                $i === 3 ? 'down' : 'up',
                $i === 1 ? 'From_VSOL_olt' : ($i === 6 ? 'SFP_RoyalNet' : null),
            );
        }

        $result = $this->driver->pollPorts();

        expect($result['polled'])->toBe(8)
            ->and($result['created'])->toBe(8);

        $ge1 = OltPort::where('olt_id', $this->olt->id)->where('if_index', 101)->first();
        expect($ge1)->not->toBeNull()
            ->and($ge1->name)->toBe('GE0/1')
            ->and($ge1->type_label)->toBe('uplink')
            ->and($ge1->is_uplink)->toBeTrue()
            ->and($ge1->oper_status)->toBe(1)
            ->and($ge1->alias)->toBe('From_VSOL_olt')
            ->and($ge1->high_speed)->toBe(1000)
            ->and($ge1->speed)->toBe(1_000_000_000)
            ->and($ge1->mtu)->toBe(1500);

        $ge3 = OltPort::where('olt_id', $this->olt->id)->where('if_index', 103)->first();
        expect($ge3->type_label)->toBe('other')
            ->and($ge3->is_uplink)->toBeFalse()
            ->and($ge3->oper_status)->toBe(2);

        // Uplink configured in the OLT config (GE0/6) wins even though the
        // description has no "link" in it.
        $ge6 = OltPort::where('olt_id', $this->olt->id)->where('if_index', 106)->first();
        expect($ge6->type_label)->toBe('uplink')
            ->and($ge6->is_uplink)->toBeTrue()
            ->and($ge6->alias)->toBe('SFP_RoyalNet');
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

        $this->driver->responses['show interface epon0/1'] = "EPON0/1 is down, line protocol is down\nMTU 1500 bytes\n";
        $this->driver->responses['show interface epon0/2'] = "EPON0/2 is up, line protocol is up\nMTU 1500 bytes\n";

        $result = $this->driver->pollPorts();

        expect($result['created'])->toBe(1)
            ->and($result['updated'])->toBe(1)
            ->and(OltPort::where('olt_id', $this->olt->id)->count())->toBe(2)
            ->and(OltPort::where('olt_id', $this->olt->id)->where('if_index', 1)->first()->oper_status)->toBe(2);
    });
});
