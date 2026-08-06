<?php

namespace App\Services\Network\OltDrivers;

use App\Models\NetworkDevice;
use App\Models\Olt;
use App\Services\Network\SnmpService;

class VsolDriver implements OltDriverInterface
{
    protected SnmpService $snmp;
    protected NetworkDevice $device;

    // VSOL OIDs (Example)
    public const OID_ONU_RX_POWER = '.1.3.6.1.4.1.37582.1.4.7.1.13'; // May vary by exact model

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
        // For SNMP, ONUs are usually indexed by an SNMP index (e.g., PON port * 1000 + ONU ID)
        // Here we simulate fetching the specific index

        // This is a simplified example. In reality, we'd walk the MAC address table to find the index first.
        $index = $this->resolveOnuIndex($ponPort, $onuMacOrId);

        if (!$index) {
            return null;
        }

        $val = $this->snmp->get(self::OID_ONU_RX_POWER . '.' . $index);

        if ($val === null || $val === '') {
            return null;
        }

        // VSOL typically returns power in 100th of dBm (e.g., -2543 = -25.43 dBm)
        // Sometimes it returns a string with "dBm" attached.
        if (preg_match('/(-?\d+)/', $val, $matches)) {
            $num = (float) $matches[1];
            // if it's very large absolute, it might be multiplied by 100
            if (abs($num) > 100) {
                return round($num / 100, 2);
            }
            return round($num, 2);
        }

        return null;
    }

    public function getOnuTxPower(string $ponPort, string $onuMacOrId): ?float
    {
        // Similar to Rx power, but different OID
        return null; // Implement specific OID
    }

    public function isOnuOnline(string $ponPort, string $onuMacOrId): bool
    {
        // Check link status OID
        return true;
    }

    protected function resolveOnuIndex(string $ponPort, string $onuMacOrId): ?string
    {
        // In a complete implementation, this would walk the MAC table OID
        // and find the index corresponding to the given MAC address.
        // For now, we assume $onuMacOrId is the SNMP index if it's numeric.
        if (is_numeric($onuMacOrId)) {
            return $onuMacOrId;
        }

        return null;
    }
}
