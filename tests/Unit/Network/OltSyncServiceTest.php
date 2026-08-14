<?php

use App\Models\NetworkDevice;
use App\Models\Olt;
use App\Models\Onu;
use App\Services\Network\OltDrivers\OltDriverInterface;
use App\Services\Network\OltSyncService;
use App\Services\Network\SnmpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FakeOltDriver implements OltDriverInterface
{
    public array $discovered;

    public function __construct(array $discovered)
    {
        $this->discovered = $discovered;
    }

    public function getOnuRxPower(string $ponPort, string $onuMacOrId): ?float
    {
        return -22.5;
    }

    public function getOnuTxPower(string $ponPort, string $onuMacOrId): ?float
    {
        return 2.1;
    }

    public function isOnuOnline(string $ponPort, string $onuMacOrId): bool
    {
        return true;
    }

    public function discoverOnus(): array
    {
        return $this->discovered;
    }

    public function getSnmpService(): SnmpService
    {
        return new SnmpService('127.0.0.1', 'public');
    }

    public function getSfpDomData(): array
    {
        return [];
    }
}

describe('OltSyncService', function () {
    beforeEach(function () {
        $this->device = NetworkDevice::factory()->create([
            'is_active' => true,
            'vendor' => \App\Enums\DeviceVendor::VSOL,
        ]);
        $this->olt = Olt::factory()->forNetworkDevice($this->device)->create([
            'last_sync_at' => null,
            'used_pon_ports' => 0,
        ]);
    });

    it('creates ONUs discovered from the OLT and updates last_sync_at', function () {
        $driver = new FakeOltDriver([
            [
                'serial_number' => 'ONU-AAA111',
                'mac_address' => 'aa:bb:cc:dd:ee:01',
                'pon_port' => 1,
                'pon_port_name' => 'PON 1',
                'ONU_id' => '1',
                'is_registered' => true,
            ],
            [
                'serial_number' => 'ONU-BBB222',
                'mac_address' => 'aa:bb:cc:dd:ee:02',
                'pon_port' => 2,
                'pon_port_name' => 'PON 2',
                'ONU_id' => '2',
                'is_registered' => true,
            ],
        ]);

        $result = app(OltSyncService::class)->syncWithDriver($this->olt, $driver);

        expect($result['discovered'])->toBe(2)
            ->and($result['created'])->toBe(2)
            ->and($result['updated'])->toBe(0)
            ->and($result['signal_ok'])->toBe(2)
            ->and($result['reachable'])->toBeTrue();

        $this->assertDatabaseHas('onus', [
            'olt_id' => $this->olt->id,
            'serial_number' => 'ONU-AAA111',
            'pon_port' => 1,
            'optical_signal_db' => -22.5,
            'operational_state' => 'online',
        ]);

        $this->assertDatabaseHas('onus', [
            'olt_id' => $this->olt->id,
            'serial_number' => 'ONU-BBB222',
            'pon_port' => 2,
        ]);

        $this->olt->refresh();
        expect($this->olt->last_sync_at)->not->toBeNull()
            ->and($this->olt->used_pon_ports)->toBe(2);
    });

    it('updates existing ONUs instead of duplicating them', function () {
        Onu::factory()->forOlt($this->olt)->create([
            'serial_number' => 'ONU-AAA111',
            'mac_address' => 'aa:bb:cc:dd:ee:01',
            'pon_port' => 1,
            'optical_signal_db' => -10.0,
        ]);

        $driver = new FakeOltDriver([
            [
                'serial_number' => 'ONU-AAA111',
                'mac_address' => 'aa:bb:cc:dd:ee:01',
                'pon_port' => 1,
                'pon_port_name' => 'PON 1',
                'ONU_id' => '1',
                'is_registered' => true,
            ],
        ]);

        $result = app(OltSyncService::class)->syncWithDriver($this->olt, $driver);

        expect($result['discovered'])->toBe(1)
            ->and($result['created'])->toBe(0)
            ->and($result['updated'])->toBe(1);

        expect(Onu::where('olt_id', $this->olt->id)->count())->toBe(1);

        $onu = Onu::where('olt_id', $this->olt->id)->first();
        expect((float) $onu->optical_signal_db)->toBe(-22.5)
            ->and($onu->operational_state)->toBe('online');
    });

    it('returns unreachable when the network device is inactive', function () {
        $this->device->update(['is_active' => false]);

        $result = app(OltSyncService::class)->sync($this->olt);

        expect($result['reachable'])->toBeFalse()
            ->and($result['discovered'])->toBe(0);

        $this->olt->refresh();
        expect($this->olt->last_sync_at)->toBeNull();
    });
});
