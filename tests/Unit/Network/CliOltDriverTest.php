<?php

namespace Tests\Unit\Network;

use App\Enums\DeviceVendor;
use App\Enums\NetworkManagementProtocol;
use App\Models\NetworkDevice;
use App\Models\Olt;
use App\Services\Network\OltDrivers\CliOltDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

class FixtureCliDriver extends CliOltDriver
{
    public array $responses = [];

    public int $commandCalls = 0;

    protected function vendorKey(): string
    {
        return 'vsol';
    }

    protected function hasAutofindCommand(): bool
    {
        return false;
    }

    protected function runCommand(string $command): string
    {
        $this->commandCalls++;

        return $this->responses[$command] ?? '';
    }

    /**
     * The exact command string the base driver will issue for a config key,
     * so fixtures stay aligned with config/olt.php.
     */
    public function command(string $key): string
    {
        return sprintf(config("olt.commands.vsol.{$key}"), '');
    }
}

describe('CliOltDriver', function () {
    beforeEach(function () {
        $this->device = NetworkDevice::factory()->create([
            'vendor' => DeviceVendor::VSOL,
            'management_protocol' => NetworkManagementProtocol::SSH,
            'ip_address' => '10.0.0.1',
            'username' => 'admin',
        ]);

        $this->olt = Olt::factory()->forNetworkDevice($this->device)->create();
        $this->driver = new FixtureCliDriver($this->olt);
    });

    it('discovers onus and merges optical signals in one pass', function () {
        $this->driver->responses = [
            $this->driver->command('onu_info') => <<<TXT
Index F/S/P  ONU ID  SN           State
0     0/1/1  1       VSOL00000001 ONLINE
1     0/1/1  2       VSOL00000002 OFFLINE
2     0/1/2  1       VSOL00000003 ONLINE
TXT,
            $this->driver->command('onu_optical') => <<<TXT
F/S/P  ONU-ID  Temp(°C)  Voltage(V)  RxPower(dBm)  TxPower(dBm)
0/1/1  1       41.2      3.31        -21.50        2.40
0/1/1  2       42.1      3.32        -18.70        2.50
0/1/2  1       40.5      3.29        -24.10        1.90
TXT,
        ];

        $onus = $this->driver->discoverOnus();

        expect($onus)->toHaveCount(3)
            ->and($onus[0]['serial_number'])->toBe('VSOL00000001')
            ->and($onus[0]['pon_port'])->toBe(1)
            ->and($onus[0]['pon_port_name'])->toBe('0/1/1')
            ->and($onus[0]['rx_power_dbm'])->toBe(-21.5)
            ->and($onus[0]['tx_power_dbm'])->toBe(2.4)
            ->and($onus[0]['is_online'])->toBeTrue()
            ->and($onus[0]['vendor_id'])->toBe('vsol')
            ->and($onus[1]['is_online'])->toBeFalse()
            ->and($onus[2]['pon_port'])->toBe(2)
            ->and($onus[2]['rx_power_dbm'])->toBe(-24.1);

        // info + optical + basic-info + descriptions tables fetched once each.
        expect($this->driver->commandCalls)->toBe(4);
    });

    it('merges vendor basic-info and onu descriptions into discovery', function () {
        $this->driver->responses = [
            $this->driver->command('onu_info') => <<<TXT
ONU-ID  Status  MAC Address
EPON0/1:1  online  a2:3e:05:23:83:f0
EPON0/1:5  online  80:14:a8:d3:8a:58
TXT,
            $this->driver->command('onu_basic_info') => <<<TXT
ONU-ID      VendorID  Model     ID            hwVer     SwVer               Type
EPON0/1:1   EDBC      EPON      A23E052383F0  393.A     V3R017C10S125       HGU
EPON0/1:5   VSOL      D401      8014A8D38A58  V2.8S     V6.0.4P1T8          SFU
TXT,
            $this->driver->command('onu_descriptions') => <<<TXT
interface epon 0/1
onu 1 description Joshim_shop
onu 5 description kanchon
TXT,
        ];

        $onus = $this->driver->discoverOnus();

        expect($onus)->toHaveCount(2)
            ->and($onus[0]['customer_name'])->toBe('Joshim_shop')
            ->and($onus[0]['ONU_type'])->toBe('HGU')
            ->and($onus[0]['vendor_id'])->toBe('EDBC')
            ->and($onus[0]['firmware_version'])->toBe('V3R017C10S125')
            ->and($onus[0]['hardware_version'])->toBe('393.A')
            ->and($onus[1]['customer_name'])->toBe('kanchon')
            ->and($onus[1]['ONU_type'])->toBe('SFU')
            ->and($onus[1]['vendor_id'])->toBe('VSOL');
    });

    it('leaves customer name and type empty when the vendor tables return nothing', function () {
        $this->driver->responses = [
            $this->driver->command('onu_info') => <<<TXT
F/S/P  ONU ID  SN           State
0/1/1  1       VSOL00000001 ONLINE
TXT,
        ];

        $onus = $this->driver->discoverOnus();

        expect($onus)->toHaveCount(1)
            ->and($onus[0]['customer_name'])->toBeNull()
            ->and($onus[0]['ONU_type'])->toBeNull()
            ->and($onus[0]['vendor_id'])->toBe('vsol');
    });

    it('serves per-onu signal lookups from a cached single command', function () {
        $this->driver->responses = [
            $this->driver->command('onu_optical') => <<<TXT
F/S/P  ONU-ID  Temp(°C)  Voltage(V)  RxPower(dBm)  TxPower(dBm)
0/1/1  1       41.2      3.31        -21.50        2.40
0/1/1  2       42.1      3.32        -18.70        2.50
TXT,
        ];

        expect($this->driver->getOnuRxPower('1', '1'))->toBe(-21.5)
            ->and($this->driver->getOnuTxPower('1', '1'))->toBe(2.4)
            ->and($this->driver->getOnuRxPower('1', '2'))->toBe(-18.7)
            ->and($this->driver->isOnuOnline('1', '1'))->toBeTrue()
            ->and($this->driver->isOnuOnline('1', '99'))->toBeFalse();

        expect($this->driver->commandCalls)->toBe(1);
    });

    it('returns empty discovery when the olt is unreachable', function () {
        $onus = $this->driver->discoverOnus();

        expect($onus)->toBe([])
            ->and($this->driver->getOnuRxPower('1', '1'))->toBeNull();
    });
});
