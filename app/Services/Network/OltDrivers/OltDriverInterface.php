<?php

namespace App\Services\Network\OltDrivers;

interface OltDriverInterface
{
    /**
     * Get the Rx (receive) optical power of a specific ONU.
     * Returns the dBm value (e.g., -24.5).
     */
    public function getOnuRxPower(string $ponPort, string $onuMacOrId): ?float;

    /**
     * Get the Tx (transmit) optical power of a specific ONU.
     * Returns the dBm value (e.g., 2.3).
     */
    public function getOnuTxPower(string $ponPort, string $onuMacOrId): ?float;

    /**
     * Check if an ONU is online.
     */
    public function isOnuOnline(string $ponPort, string $onuMacOrId): bool;
}
