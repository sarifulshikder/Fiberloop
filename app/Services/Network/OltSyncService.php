<?php

namespace App\Services\Network;

use App\Models\Olt;
use App\Models\Onu;
use App\Services\Network\OltDrivers\OltDriverFactory;
use App\Services\Network\OltDrivers\OltDriverInterface;
use Exception;
use Illuminate\Support\Facades\Log;

class OltSyncService
{
    /**
     * Discover ONUs from the OLT, upsert them, and refresh each ONU's optical
     * signal. Updates the OLT's last_sync_at on completion.
     *
     * Returns a summary array:
     * [
     *   'discovered' => int,   // ONUs reported by the OLT
     *   'created'    => int,   // new ONU rows inserted
     *   'updated'    => int,   // existing ONU rows refreshed
     *   'signal_ok'  => int,   // ONUs whose optical signal was read
     *   'signal_fail'=> int,   // ONUs whose optical signal could not be read
     *   'reachable'  => bool,  // whether the OLT responded to discovery
     * ]
     */
    public function sync(Olt $olt): array
    {
        $device = $olt->networkDevice;

        if (!$device || !$device->is_active) {
            return [
                'discovered' => 0,
                'created' => 0,
                'updated' => 0,
                'signal_ok' => 0,
                'signal_fail' => 0,
                'reachable' => false,
            ];
        }

        try {
            $driver = OltDriverFactory::make($olt);
        } catch (Exception $e) {
            Log::warning("OltSyncService: no driver for OLT {$olt->id}", ['error' => $e->getMessage()]);

            return [
                'discovered' => 0,
                'created' => 0,
                'updated' => 0,
                'signal_ok' => 0,
                'signal_fail' => 0,
                'reachable' => false,
            ];
        }

        return $this->syncWithDriver($olt, $driver);
    }

    /**
     * Discover ONUs from the OLT using a pre-resolved driver, upsert them, and
     * refresh each ONU's optical signal. Updates the OLT's last_sync_at.
     *
     * Extracted from sync() so tests can exercise the full pipeline with a fake
     * driver without hijacking OltDriverFactory via a process-wide overload mock.
     */
    public function syncWithDriver(Olt $olt, OltDriverInterface $driver): array
    {
        $discovered = $driver->discoverOnus();

        if (empty($discovered)) {
            $olt->update([
                'last_sync_at' => now(),
                'used_pon_ports' => $this->countUsedPonPorts($olt),
            ]);

            return [
                'discovered' => 0,
                'created' => 0,
                'updated' => 0,
                'signal_ok' => 0,
                'signal_fail' => 0,
                'reachable' => true,
            ];
        }

        // Preload all existing ONUs for this OLT to avoid N+1 queries
        $existingOnus = Onu::where('olt_id', $olt->id)->get();

        // Build lookup maps for fast matching
        $onuBySerial = [];
        $onuByMac = [];
        $macUsage = []; // Track which ONUs use each MAC address

        foreach ($existingOnus as $onu) {
            if ($onu->serial_number !== null) {
                $onuBySerial[$onu->serial_number] = $onu;
            }
            if ($onu->mac_address !== null) {
                $onuByMac[$onu->mac_address] = $onu;
                $macUsage[$onu->mac_address] = ($macUsage[$onu->mac_address] ?? 0) + 1;
            }
        }

        $created = 0;
        $updated = 0;
        $signalOk = 0;
        $signalFail = 0;
        $onuIdsForSignalRefresh = [];

        // First pass: create or update all ONUs (without signal refresh)
        foreach ($discovered as $data) {
            $serial = $data['serial_number'] ?? null;
            $mac = $data['mac_address'] ?? null;

            if (empty($serial) && empty($mac)) {
                continue;
            }

            $onu = null;

            // Try to find by serial first
            if (!empty($serial) && isset($onuBySerial[$serial])) {
                $onu = $onuBySerial[$serial];
            }
            // Try to find by MAC
            if ($onu === null && !empty($mac) && isset($onuByMac[$mac])) {
                $onu = $onuByMac[$mac];
            }

            $attributes = [
                'olt_id' => $olt->id,
                'tenant_id' => $olt->tenant_id,
                'serial_number' => $serial,
                'pon_port' => $data['pon_port'] ?? null,
                'pon_port_name' => $data['pon_port_name'] ?? null,
                'ONU_id' => $data['ONU_id'] ?? null,
                'is_registered' => $data['is_registered'] ?? true,
                'registered_at' => $data['registered_at'] ?? null,
                'vendor_id' => $data['vendor_id'] ?? null,
                'firmware_version' => $data['firmware_version'] ?? null,
                'hardware_version' => $data['hardware_version'] ?? null,
                'ONU_type' => $data['ONU_type'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
            ];

            // Handle MAC address - only update if not already used by another ONU
            if (!empty($mac)) {
                if ($onu === null) {
                    // New ONU, check if MAC is already in use
                    if (!isset($macUsage[$mac]) || $macUsage[$mac] === 0) {
                        $attributes['mac_address'] = $mac;
                    }
                } else {
                    // Existing ONU - only update MAC if it's not used by another ONU
                    if (!isset($macUsage[$mac]) || $macUsage[$mac] === 1 || ($macUsage[$mac] > 1 && $onu->mac_address === $mac)) {
                        $attributes['mac_address'] = $mac;
                    }
                }
            } else {
                $attributes['mac_address'] = null;
            }

            if ($onu === null) {
                // Create new ONU
                $newOnu = Onu::create($attributes);
                $created++;
                $onuIdsForSignalRefresh[] = $newOnu->id;
                // Update lookup maps
                if ($serial !== null) {
                    $onuBySerial[$serial] = $newOnu;
                }
                if ($mac !== null && isset($attributes['mac_address'])) {
                    $onuByMac[$mac] = $newOnu;
                    $macUsage[$mac] = ($macUsage[$mac] ?? 0) + 1;
                }
            } else {
                // Update existing ONU
                $onu->update($attributes);
                $updated++;
                $onuIdsForSignalRefresh[] = $onu->id;
            }
        }

        // Second pass: refresh signal for all updated/created ONUs
        // Preload the ONUs we need to refresh
        $onusToRefresh = Onu::whereIn('id', $onuIdsForSignalRefresh)->get();

        foreach ($onusToRefresh as $onu) {
            if ($this->refreshSignal($driver, $onu)) {
                $signalOk++;
            } else {
                $signalFail++;
            }
        }

        // Update OLT used PON ports count
        $usedPonPorts = $this->countUsedPonPorts($olt);

        $olt->update([
            'last_sync_at' => now(),
            'used_pon_ports' => $usedPonPorts,
        ]);

        return [
            'discovered' => count($discovered),
            'created' => $created,
            'updated' => $updated,
            'signal_ok' => $signalOk,
            'signal_fail' => $signalFail,
            'reachable' => true,
        ];
    }

    /**
     * Create or update an ONU row from a discovery record, matching by
     * serial_number first, then mac_address.
     */
    protected function upsertOnu(Olt $olt, array $data): ?Onu
    {
        $serial = $data['serial_number'] ?? null;
        $mac = $data['mac_address'] ?? null;

        if (empty($serial) && empty($mac)) {
            return null;
        }

        $onu = null;
        if (!empty($serial)) {
            $onu = Onu::where('olt_id', $olt->id)->where('serial_number', $serial)->first();
        }
        if ($onu === null && !empty($mac)) {
            $onu = Onu::where('olt_id', $olt->id)->where('mac_address', $mac)->first();
        }

        $attributes = [
            'olt_id' => $olt->id,
            'tenant_id' => $olt->tenant_id,
            'serial_number' => $serial,
            'pon_port' => $data['pon_port'] ?? null,
            'pon_port_name' => $data['pon_port_name'] ?? null,
            'ONU_id' => $data['ONU_id'] ?? null,
            'is_registered' => $data['is_registered'] ?? true,
            'registered_at' => $data['registered_at'] ?? null,
            'vendor_id' => $data['vendor_id'] ?? null,
            'firmware_version' => $data['firmware_version'] ?? null,
            'hardware_version' => $data['hardware_version'] ?? null,
            'ONU_type' => $data['ONU_type'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
        ];

        // Only update MAC address if it's provided and either the ONU is new
        // or the MAC address is not already in use by another ONU
        if (!empty($mac)) {
            if ($onu === null) {
                $attributes['mac_address'] = $mac;
            } else {
                // Check if this MAC is already used by a different ONU
                $existingWithMac = Onu::where('mac_address', $mac)->where('id', '!=', $onu->id)->first();
                if ($existingWithMac === null) {
                    $attributes['mac_address'] = $mac;
                }
                // If MAC exists on another ONU, skip updating it to avoid constraint violation
            }
        } else {
            $attributes['mac_address'] = null;
        }

        if ($onu === null) {
            return Onu::create($attributes);
        }

        $onu->update($attributes);

        return $onu;
    }

    /**
     * Poll a single ONU's optical signal and operational state via the driver.
     * Returns true if the signal was read successfully.
     */
    protected function refreshSignal(OltDriverInterface $driver, Onu $onu): bool
    {
        try {
            $rxPower = $driver->getOnuRxPower((string) $onu->pon_port, (string) $onu->ONU_id);
            $txPower = $driver->getOnuTxPower((string) $onu->pon_port, (string) $onu->ONU_id);
            $isOnline = $driver->isOnuOnline((string) $onu->pon_port, (string) $onu->ONU_id);

            $onu->update([
                'optical_signal_db' => $rxPower,
                'tx_power_db' => $txPower,
                'rx_power_db' => $rxPower,
                'operational_state' => $isOnline ? 'online' : 'offline',
                'last_signal_check_at' => now(),
            ]);

            return $rxPower !== null;
        } catch (Exception $e) {
            Log::warning("OltSyncService: failed to poll ONU {$onu->id}", ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Count distinct PON ports currently in use by ONUs on this OLT.
     */
    protected function countUsedPonPorts(Olt $olt): int
    {
        return Onu::where('olt_id', $olt->id)
            ->whereNotNull('pon_port')
            ->distinct('pon_port')
            ->count('pon_port');
    }
}
