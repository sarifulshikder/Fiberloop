<?php

namespace App\Services\Network\OltDrivers;

/**
 * Huawei MA5600/MA5800 OLT driver reading ONU data over SSH CLI.
 *
 * Discovery runs per PON port (`display ont info {f/s/p} all`) plus
 * `display ont autofind all` for unregistered ONUs. The ports to iterate come
 * from the OLT record's configuration, falling back to total_pon_ports.
 */
class HuaweiCliDriver extends CliOltDriver
{
    protected function vendorKey(): string
    {
        return 'huawei';
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

        $prefix = $this->olt->configuration['frame_slot'] ?? '0/1';

        return array_map(fn (int $i) => $prefix . '/' . $i, range(0, $total - 1));
    }
}
