<?php

namespace App\Services\Network\OltDrivers;

use App\Enums\NetworkManagementProtocol;
use App\Models\Olt;
use InvalidArgumentException;

class OltDriverFactory
{
    /**
     * Pick a driver for an OLT based on the device's management protocol and
     * vendor. SSH CLI drivers are the universal option (every OLT has a CLI);
     * SNMP drivers remain the fallback where vendor MIBs work.
     */
    public static function make(Olt $olt): OltDriverInterface
    {
        $device = $olt->networkDevice;

        $vendor = strtolower($device->vendor?->value ?? '');
        $protocol = $device->management_protocol?->value
            ?? NetworkManagementProtocol::SNMP->value;

        if ($protocol === NetworkManagementProtocol::SSH->value) {
            return match ($vendor) {
                'vsol' => new VsolCliDriver($olt),
                'bdcom' => new BdcomCliDriver($olt),
                'huawei' => new HuaweiCliDriver($olt),
                'zte' => new ZteCliDriver($olt),
                default => throw new InvalidArgumentException("No SSH CLI OLT driver found for vendor: {$vendor}"),
            };
        }

        if ($protocol === NetworkManagementProtocol::SNMP->value) {
            return match ($vendor) {
                'vsol' => new VsolDriver($olt),
                'bdcom' => new BdcomDriver($olt),
                default => throw new InvalidArgumentException("No SNMP OLT driver found for vendor: {$vendor}"),
            };
        }

        throw new InvalidArgumentException("Protocol '{$protocol}' is not an OLT management protocol; use SSH or SNMP.");
    }
}
