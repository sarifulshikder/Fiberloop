<?php

namespace App\Services\Network\OltDrivers;

use App\Models\NetworkDevice;
use App\Models\Olt;
use App\Services\Network\SnmpService;

class BdcomDriver implements OltDriverInterface
{
    protected SnmpService $snmp;
    protected NetworkDevice $device;

    // BDCOM specific OIDs
    public const OID_ONU_RX_POWER = '.1.3.6.1.4.1.3320.101.10.5.1.5';
    public const OID_ONU_TX_POWER = '.1.3.6.1.4.1.3320.101.10.5.1.6';

    public function __construct(Olt $olt)
    {
        $this->device = $olt->networkDevice;
        $this->snmp = new SnmpService(
            $this->device->ip_address,
            $this->device->snmp_community ?? 'public',
            $this->device->snmp_version ?? '2c'
        );
    }

    public function getOnuRxPower(string $ponPort, string $onuMacOrId): ?float
    {
        $index = $this->resolveOnuIndex($ponPort, $onuMacOrId);
        if (!$index) {
            return null;
        }

        $val = $this->snmp->get(self::OID_ONU_RX_POWER . '.' . $index);
        
        if ($val === null || $val === '') {
            return null;
        }

        // BDCOM typically returns in 0.1 uW or dBm * 10
        if (preg_match('/(-?\d+)/', $val, $matches)) {
            $num = (float) $matches[1];
            if (abs($num) > 100) {
                return round($num / 10, 2);
            }
            return round($num, 2);
        }

        return null;
    }

    public function getOnuTxPower(string $ponPort, string $onuMacOrId): ?float
    {
        $index = $this->resolveOnuIndex($ponPort, $onuMacOrId);
        if (!$index) {
            return null;
        }

        $val = $this->snmp->get(self::OID_ONU_TX_POWER . '.' . $index);

        if ($val === null || $val === '') {
            return null;
        }

        if (preg_match('/(-?\d+)/', $val, $matches)) {
            $num = (float) $matches[1];
            if (abs($num) > 100) {
                return round($num / 10, 2);
            }
            return round($num, 2);
        }

        return null;
    }

    public function isOnuOnline(string $ponPort, string $onuMacOrId): bool
    {
        // Check link status OID (e.g. .1.3.6.1.4.1.3320.101.10.1.1.26)
        // For simplicity returning true
        return true;
    }

    protected function resolveOnuIndex(string $ponPort, string $onuMacOrId): ?string
    {
        if (is_numeric($onuMacOrId)) {
            return $onuMacOrId;
        }

        return null;
    }
}
