<?php

namespace Tests\Unit\Network;

use App\Enums\DeviceVendor;
use App\Enums\NetworkManagementProtocol;
use App\Models\NetworkDevice;
use App\Models\Olt;
use App\Services\Network\OltDrivers\BdcomCliDriver;
use App\Services\Network\OltDrivers\BdcomDriver;
use App\Services\Network\OltDrivers\HuaweiCliDriver;
use App\Services\Network\OltDrivers\OltDriverFactory;
use App\Services\Network\OltDrivers\VsolCliDriver;
use App\Services\Network\OltDrivers\VsolDriver;
use App\Services\Network\OltDrivers\ZteCliDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('OltDriverFactory', function () {
    beforeEach(function () {
        $this->device = NetworkDevice::factory()->create();
        $this->olt = Olt::factory()->forNetworkDevice($this->device)->create();
    });

    it('defaults to snmp when no protocol is set', function () {
        $this->device->update(['vendor' => DeviceVendor::VSOL]);

        expect($this->device->refresh()->management_protocol)->toBe(NetworkManagementProtocol::SNMP);

        $driver = OltDriverFactory::make($this->olt->refresh());

        expect($driver)->toBeInstanceOf(VsolDriver::class);
    });

    it('picks the snmp driver when protocol is snmp', function () {
        $this->device->update([
            'vendor' => DeviceVendor::BDCOM,
            'management_protocol' => NetworkManagementProtocol::SNMP,
        ]);

        $driver = OltDriverFactory::make($this->olt->refresh());

        expect($driver)->toBeInstanceOf(BdcomDriver::class);
    });

    it('picks the cli driver when protocol is ssh', function () {
        $this->device->update([
            'vendor' => DeviceVendor::VSOL,
            'management_protocol' => NetworkManagementProtocol::SSH,
        ]);

        $driver = OltDriverFactory::make($this->olt->refresh());

        expect($driver)->toBeInstanceOf(VsolCliDriver::class);
    });

    it('picks the bdcom cli driver for ssh', function () {
        $this->device->update([
            'vendor' => DeviceVendor::BDCOM,
            'management_protocol' => NetworkManagementProtocol::SSH,
        ]);

        $driver = OltDriverFactory::make($this->olt->refresh());

        expect($driver)->toBeInstanceOf(BdcomCliDriver::class);
    });

    it('maps ssh protocol to huawei and zte cli drivers', function () {
        $this->device->update([
            'vendor' => DeviceVendor::HUAWEI,
            'management_protocol' => NetworkManagementProtocol::SSH,
        ]);

        expect(OltDriverFactory::make($this->olt->refresh()))->toBeInstanceOf(HuaweiCliDriver::class);

        $this->device->update([
            'vendor' => DeviceVendor::ZTE,
            'management_protocol' => NetworkManagementProtocol::SSH,
        ]);

        expect(OltDriverFactory::make($this->olt->refresh()))->toBeInstanceOf(ZteCliDriver::class);
    });

    it('throws for vendors without a cli driver', function () {
        $this->device->update([
            'vendor' => DeviceVendor::NOKIA,
            'management_protocol' => NetworkManagementProtocol::SSH,
        ]);

        OltDriverFactory::make($this->olt->refresh());
    })->throws(InvalidArgumentException::class);
});
