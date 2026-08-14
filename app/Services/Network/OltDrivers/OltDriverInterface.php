<?php

namespace App\Services\Network\OltDrivers;

use App\Services\Network\SnmpService;

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

    /**
     * Discover the ONUs currently registered on the OLT.
     *
     * Returns an array of associative arrays, each describing one ONU:
     * [
     *   'serial_number'  => string,
     *   'mac_address'    => string|null,
     *   'pon_port'       => int|null,
     *   'pon_port_name'  => string|null,
     *   'ONU_id'         => string|null,
     *   'is_registered'  => bool,
     *   'registered_at'  => \DateTimeInterface|string|null,
     *   'vendor_id'      => string|null,
     *   'firmware_version' => string|null,
     *   'hardware_version' => string|null,
     *   'ONU_type'       => string|null,
     * ]
     *
     * Implementations should return an empty array when the OLT is unreachable
     * or the vendor-specific discovery OIDs are not available.
     */
    public function discoverOnus(): array;

    /**
     * Get the underlying SNMP service for direct OID queries.
     */
    public function getSnmpService(): SnmpService;

    /**
     * Get SFP DOM (Digital Optical Monitoring) data for all ports.
     *
     * Returns array keyed by ifIndex:
     * [
     *   ifIndex => [
     *     'vendor' => string,
     *     'part_number' => string,
     *     'serial' => string,
     *     'revision' => string,
     *     'date_code' => string,
     *     'wavelength' => string,
     *     'tx_power_dbm' => float,
     *     'rx_power_dbm' => float,
     *     'temperature_c' => float,
     *     'voltage_v' => float,
     *     'tx_bias_ma' => float,
     *     'rx_power_mw' => float,
     *     'tx_power_mw' => float,
     *     'thresholds' => array,
     *     'alarms' => array,
     *     'warnings' => array,
     *   ],
     * ]
     */
    public function getSfpDomData(): array;
}
