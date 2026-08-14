<?php

namespace App\Services\Network;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Canonical, vendor-agnostic representation of an ONU discovered on an OLT.
 * Every OLT driver (SNMP or SSH CLI) normalizes its output into this shape so
 * the rest of the application never sees vendor-specific quirks.
 */
class OnuInfo implements Arrayable
{
    public function __construct(
        public readonly ?string $serialNumber = null,
        public readonly ?string $macAddress = null,
        public readonly string|int|null $ponPort = null,
        public readonly ?string $ponPortName = null,
        public readonly string|int|null $onuId = null,
        public readonly bool $isRegistered = true,
        public readonly ?string $registeredAt = null,
        public readonly ?string $vendorId = null,
        public readonly ?string $firmwareVersion = null,
        public readonly ?string $hardwareVersion = null,
        public readonly ?string $onuType = null,
        public readonly ?string $customerName = null,
        public readonly ?float $rxPowerDbm = null,
        public readonly ?float $txPowerDbm = null,
        public readonly ?bool $isOnline = null,
    ) {
    }

    /**
     * Return a copy with the given fields overridden.
     */
    public function with(array $overrides): self
    {
        $current = $this->toArray();

        return new self(
            serialNumber: $overrides['serial_number'] ?? $current['serial_number'],
            macAddress: $overrides['mac_address'] ?? $current['mac_address'],
            ponPort: $overrides['pon_port'] ?? $current['pon_port'],
            ponPortName: $overrides['pon_port_name'] ?? $current['pon_port_name'],
            onuId: $overrides['ONU_id'] ?? $current['ONU_id'],
            isRegistered: $overrides['is_registered'] ?? $current['is_registered'],
            registeredAt: $overrides['registered_at'] ?? $current['registered_at'],
            vendorId: $overrides['vendor_id'] ?? $current['vendor_id'],
            firmwareVersion: $overrides['firmware_version'] ?? $current['firmware_version'],
            hardwareVersion: $overrides['hardware_version'] ?? $current['hardware_version'],
            onuType: $overrides['ONU_type'] ?? $current['ONU_type'],
            customerName: $overrides['customer_name'] ?? $current['customer_name'],
            rxPowerDbm: $overrides['rx_power_dbm'] ?? $current['rx_power_dbm'],
            txPowerDbm: $overrides['tx_power_dbm'] ?? $current['tx_power_dbm'],
            isOnline: $overrides['is_online'] ?? $current['is_online'],
        );
    }

    public function toArray(): array
    {
        return [
            'serial_number' => $this->serialNumber,
            'mac_address' => $this->macAddress,
            'pon_port' => $this->ponPort,
            'pon_port_name' => $this->ponPortName,
            'ONU_id' => $this->onuId,
            'is_registered' => $this->isRegistered,
            'registered_at' => $this->registeredAt,
            'vendor_id' => $this->vendorId,
            'firmware_version' => $this->firmwareVersion,
            'hardware_version' => $this->hardwareVersion,
            'ONU_type' => $this->onuType,
            'customer_name' => $this->customerName,
            'rx_power_dbm' => $this->rxPowerDbm,
            'tx_power_dbm' => $this->txPowerDbm,
            'is_online' => $this->isOnline,
        ];
    }
}
