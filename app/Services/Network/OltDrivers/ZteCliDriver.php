<?php

namespace App\Services\Network\OltDrivers;

/**
 * ZTE C320/C600 OLT driver reading ONU data over SSH CLI.
 *
 * Discovery runs per PON port (`show gpon onu baseinfo gpon-onu_{f/s/p}:all`)
 * plus `show gpon onu uncfg` for unconfigured ONUs. Optical data is read with
 * `show gpon onu optical-info gpon-onu_{f/s/p}:all`.
 */
class ZteCliDriver extends CliOltDriver
{
    protected function vendorKey(): string
    {
        return 'zte';
    }

    protected function hasAutofindCommand(): bool
    {
        return true;
    }

    protected function ponPortIdentifiers(): array
    {
        return $this->generatePonPortIdentifiers();
    }

    protected function generatePonPortIdentifiers(): array
    {
        $ports = $this->olt->configuration['pon_ports'] ?? null;

        if (is_array($ports) && $ports !== []) {
            return $ports;
        }

        $total = (int) ($this->olt->total_pon_ports ?? 0);

        if ($total <= 0) {
            return [];
        }

        $prefix = $this->olt->configuration['frame_slot'] ?? '1/1';

        return array_map(fn (int $i) => $prefix . '/' . $i, range(1, $total));
    }
}
