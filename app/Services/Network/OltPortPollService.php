<?php

namespace App\Services\Network;

use App\Models\Olt;
use App\Models\OltPort;
use App\Services\Network\OltDrivers\OltDriverFactory;
use App\Services\Network\OltDrivers\SupportsCliPortPoll;
use Exception;
use Illuminate\Support\Facades\Log;

class OltPortPollService
{
    // Standard IF-MIB OIDs
    public const OID_IF_DESCR = '.1.3.6.1.2.1.2.2.1.2';
    public const OID_IF_ALIAS = '.1.3.6.1.2.1.31.1.1.1.18';
    public const OID_IF_TYPE = '.1.3.6.1.2.1.2.2.1.3';
    public const OID_IF_MTU = '.1.3.6.1.2.1.2.2.1.4';
    public const OID_IF_SPEED = '.1.3.6.1.2.1.2.2.1.5';
    public const OID_IF_HIGH_SPEED = '.1.3.6.1.2.1.31.1.1.1.15';
    public const OID_IF_PHYS_ADDRESS = '.1.3.6.1.2.1.2.2.1.6';
    public const OID_IF_ADMIN_STATUS = '.1.3.6.1.2.1.2.2.1.7';
    public const OID_IF_OPER_STATUS = '.1.3.6.1.2.1.2.2.1.8';
    public const OID_IF_LAST_CHANGE = '.1.3.6.1.2.1.2.2.1.9';
    public const OID_IF_IN_OCTETS = '.1.3.6.1.2.1.2.2.1.10';
    public const OID_IF_OUT_OCTETS = '.1.3.6.1.2.1.2.2.1.16';
    public const OID_IF_IN_ERRORS = '.1.3.6.1.2.1.2.2.1.14';
    public const OID_IF_OUT_ERRORS = '.1.3.6.1.2.1.2.2.1.20';
    public const OID_IF_IN_DISCARDS = '.1.3.6.1.2.1.2.2.1.13';
    public const OID_IF_OUT_DISCARDS = '.1.3.6.1.2.1.2.2.1.19';
    public const OID_IF_IN_UCAST_PKTS = '.1.3.6.1.2.1.2.2.1.11';
    public const OID_IF_OUT_UCAST_PKTS = '.1.3.6.1.2.1.2.2.1.17';

    // ENTITY-MIB for SFP info
    public const OID_ENT_PHYSICAL_DESCR = '.1.3.6.1.2.1.47.1.1.1.1.2';
    public const OID_ENT_PHYSICAL_VENDOR_TYPE = '.1.3.6.1.2.1.47.1.1.1.1.3';
    public const OID_ENT_PHYSICAL_CONT_NAME = '.1.3.6.1.2.1.47.1.1.1.1.4';
    public const OID_ENT_PHYSICAL_NAME = '.1.3.6.1.2.1.47.1.1.1.1.7';
    public const OID_ENT_PHYSICAL_HW_REV = '.1.3.6.1.2.1.47.1.1.1.1.8';
    public const OID_ENT_PHYSICAL_FW_REV = '.1.3.6.1.2.1.47.1.1.1.1.9';
    public const OID_ENT_PHYSICAL_SW_REV = '.1.3.6.1.2.1.47.1.1.1.1.10';
    public const OID_ENT_PHYSICAL_SERIAL_NUM = '.1.3.6.1.2.1.47.1.1.1.1.11';
    public const OID_ENT_PHYSICAL_MFG_NAME = '.1.3.6.1.2.1.47.1.1.1.1.12';
    public const OID_ENT_PHYSICAL_MODEL_NAME = '.1.3.6.1.2.1.47.1.1.1.1.13';
    public const OID_ENT_PHYSICAL_ALIAS = '.1.3.6.1.2.1.47.1.1.1.1.14';
    public const OID_ENT_PHYSICAL_ASSET_ID = '.1.3.6.1.2.1.47.1.1.1.1.15';
    public const OID_ENT_PHYSICAL_IS_FRU = '.1.3.6.1.2.1.47.1.1.1.1.16';
    public const OID_ENT_PHYSICAL_MFG_DATE = '.1.3.6.1.2.1.47.1.1.1.1.17';
    public const OID_ENT_PHYSICAL_URIS = '.1.3.6.1.2.1.47.1.1.1.1.18';
    public const OID_ENT_PHYSICAL_UUID = '.1.3.6.1.2.1.47.1.1.1.1.19';
    public const OID_ENT_PHYSICAL_CLASS = '.1.3.6.1.2.1.47.1.1.1.1.5'; // 9=port, 10=stack, etc.

    /**
     * Poll all ports on an OLT and update/create OltPort records.
     *
     * Returns summary array.
     */
    public function poll(Olt $olt): array
    {
        $device = $olt->networkDevice;

        if (!$device || !$device->is_active) {
            return [
                'polled' => 0,
                'created' => 0,
                'updated' => 0,
                'reachable' => false,
            ];
        }

        try {
            $driver = OltDriverFactory::make($olt);
        } catch (Exception $e) {
            Log::warning("OltPortPollService: no driver for OLT {$olt->id}", ['error' => $e->getMessage()]);
            return [
                'polled' => 0,
                'created' => 0,
                'updated' => 0,
                'reachable' => false,
            ];
        }

        // OLTs without a reachable SNMP service (e.g. VSOL over telnet) poll
        // their ports directly over the CLI.
        if ($driver instanceof SupportsCliPortPoll) {
            return $driver->pollPorts();
        }

        $snmp = $driver->getSnmpService();

        // Walk all standard IF-MIB tables
        $ifDescr = $snmp->walk(self::OID_IF_DESCR);
        $ifAlias = $snmp->walk(self::OID_IF_ALIAS);
        $ifType = $snmp->walk(self::OID_IF_TYPE);
        $ifMtu = $snmp->walk(self::OID_IF_MTU);
        $ifSpeed = $snmp->walk(self::OID_IF_SPEED);
        $ifHighSpeed = $snmp->walk(self::OID_IF_HIGH_SPEED);
        $ifPhysAddress = $snmp->walk(self::OID_IF_PHYS_ADDRESS);
        $ifAdminStatus = $snmp->walk(self::OID_IF_ADMIN_STATUS);
        $ifOperStatus = $snmp->walk(self::OID_IF_OPER_STATUS);
        $ifLastChange = $snmp->walk(self::OID_IF_LAST_CHANGE);
        $ifInOctets = $snmp->walk(self::OID_IF_IN_OCTETS);
        $ifOutOctets = $snmp->walk(self::OID_IF_OUT_OCTETS);
        $ifInErrors = $snmp->walk(self::OID_IF_IN_ERRORS);
        $ifOutErrors = $snmp->walk(self::OID_IF_OUT_ERRORS);
        $ifInDiscards = $snmp->walk(self::OID_IF_IN_DISCARDS);
        $ifOutDiscards = $snmp->walk(self::OID_IF_OUT_DISCARDS);
        $ifInUcastPkts = $snmp->walk(self::OID_IF_IN_UCAST_PKTS);
        $ifOutUcastPkts = $snmp->walk(self::OID_IF_OUT_UCAST_PKTS);

        if (empty($ifDescr)) {
            return [
                'polled' => 0,
                'created' => 0,
                'updated' => 0,
                'reachable' => true,
            ];
        }

        // Get ENTITY-MIB for SFP details
        $entDescr = $snmp->walk(self::OID_ENT_PHYSICAL_DESCR);
        $entVendorType = $snmp->walk(self::OID_ENT_PHYSICAL_VENDOR_TYPE);
        $entName = $snmp->walk(self::OID_ENT_PHYSICAL_NAME);
        $entHwRev = $snmp->walk(self::OID_ENT_PHYSICAL_HW_REV);
        $entFwRev = $snmp->walk(self::OID_ENT_PHYSICAL_FW_REV);
        $entSerialNum = $snmp->walk(self::OID_ENT_PHYSICAL_SERIAL_NUM);
        $entMfgName = $snmp->walk(self::OID_ENT_PHYSICAL_MFG_NAME);
        $entModelName = $snmp->walk(self::OID_ENT_PHYSICAL_MODEL_NAME);
        $entMfgDate = $snmp->walk(self::OID_ENT_PHYSICAL_MFG_DATE);
        $entClass = $snmp->walk(self::OID_ENT_PHYSICAL_CLASS);

        // Get vendor-specific SFP DOM data
        $sfpDomData = $driver->getSfpDomData();

        $created = 0;
        $updated = 0;
        $polled = 0;

        // Preload existing ports for this OLT
        $existingPorts = OltPort::where('olt_id', $olt->id)->get()->keyBy('if_index');

        foreach ($ifDescr as $oid => $name) {
            $normalizedOid = $this->normalizeOid($oid);
            if (!preg_match('/\.(\d+)$/', $normalizedOid, $m)) {
                continue;
            }
            $ifIndex = (int) $m[1];

            $name = trim($name, ' ."');
            $alias = trim($ifAlias[$oid] ?? '', ' ."');
            $type = isset($ifType[$oid]) ? (int) preg_replace('/\D/', '', $ifType[$oid]) : null;
            $mtu = isset($ifMtu[$oid]) ? (int) preg_replace('/\D/', '', $ifMtu[$oid]) : null;
            $speed = isset($ifSpeed[$oid]) ? (int) preg_replace('/\D/', '', $ifSpeed[$oid]) : null;
            $highSpeed = isset($ifHighSpeed[$oid]) ? (int) preg_replace('/\D/', '', $ifHighSpeed[$oid]) : null;
            $mac = $this->formatMacAddress($ifPhysAddress[$oid] ?? '');
            $adminStatus = isset($ifAdminStatus[$oid]) ? (int) preg_replace('/\D/', '', $ifAdminStatus[$oid]) : null;
            $operStatus = isset($ifOperStatus[$oid]) ? (int) preg_replace('/\D/', '', $ifOperStatus[$oid]) : null;
            $lastChange = isset($ifLastChange[$oid]) ? (int) preg_replace('/\D/', '', $ifLastChange[$oid]) : null;
            $inOctets = isset($ifInOctets[$oid]) ? (int) preg_replace('/\D/', '', $ifInOctets[$oid]) : null;
            $outOctets = isset($ifOutOctets[$oid]) ? (int) preg_replace('/\D/', '', $ifOutOctets[$oid]) : null;
            $inErrors = isset($ifInErrors[$oid]) ? (int) preg_replace('/\D/', '', $ifInErrors[$oid]) : null;
            $outErrors = isset($ifOutErrors[$oid]) ? (int) preg_replace('/\D/', '', $ifOutErrors[$oid]) : null;
            $inDiscards = isset($ifInDiscards[$oid]) ? (int) preg_replace('/\D/', '', $ifInDiscards[$oid]) : null;
            $outDiscards = isset($ifOutDiscards[$oid]) ? (int) preg_replace('/\D/', '', $ifOutDiscards[$oid]) : null;
            $inUcastPkts = isset($ifInUcastPkts[$oid]) ? (int) preg_replace('/\D/', '', $ifInUcastPkts[$oid]) : null;
            $outUcastPkts = isset($ifOutUcastPkts[$oid]) ? (int) preg_replace('/\D/', '', $ifOutUcastPkts[$oid]) : null;

            // Determine type label from name/description
            $typeLabel = $this->determineTypeLabel($name, $alias, $type, $highSpeed);

            // Find matching ENTITY-MIB entry for SFP info
            $sfpInfo = $this->findSfpEntityInfo($ifIndex, $entDescr, $entVendorType, $entName, $entHwRev, $entFwRev, $entSerialNum, $entMfgName, $entModelName, $entMfgDate, $entClass);

            // Get SFP DOM data for this port
            $domData = $sfpDomData[$ifIndex] ?? [];

            $attributes = [
                'olt_id' => $olt->id,
                'tenant_id' => $olt->tenant_id,
                'network_device_id' => $device->id,
                'if_index' => $ifIndex,
                'name' => $name,
                'alias' => $alias ?: null,
                'if_type' => $type,
                'type_label' => $typeLabel,
                'admin_status' => $adminStatus,
                'oper_status' => $operStatus,
                'speed' => $speed ?: null,
                'high_speed' => $highSpeed ?: null,
                'mtu' => $mtu ?: null,
                'mac_address' => $mac ?: null,
                'is_active' => true,
                'last_polled_at' => now(),
                'poll_error' => false,
                'poll_error_message' => null,

                // SFP static info
                'sfp_present' => !empty($sfpInfo['vendor']) || !empty($domData),
                'sfp_vendor' => $sfpInfo['vendor'] ?? $domData['vendor'] ?? null,
                'sfp_part_number' => $sfpInfo['part_number'] ?? $domData['part_number'] ?? null,
                'sfp_serial_number' => $sfpInfo['serial'] ?? $domData['serial'] ?? null,
                'sfp_revision' => $sfpInfo['revision'] ?? $domData['revision'] ?? null,
                'sfp_date_code' => $sfpInfo['date_code'] ?? $domData['date_code'] ?? null,
                'sfp_connector_type' => $sfpInfo['connector'] ?? null,
                'sfp_wavelength' => $sfpInfo['wavelength'] ?? $domData['wavelength'] ?? null,
                'sfp_distance' => $sfpInfo['distance'] ?? null,
                'sfp_standard' => $sfpInfo['standard'] ?? null,

                // SFP DOM real-time
                'sfp_tx_power_dbm' => $domData['tx_power_dbm'] ?? null,
                'sfp_rx_power_dbm' => $domData['rx_power_dbm'] ?? null,
                'sfp_temperature_c' => $domData['temperature_c'] ?? null,
                'sfp_voltage_v' => $domData['voltage_v'] ?? null,
                'sfp_tx_bias_ma' => $domData['tx_bias_ma'] ?? null,
                'sfp_rx_power_mw' => $domData['rx_power_mw'] ?? null,
                'sfp_tx_power_mw' => $domData['tx_power_mw'] ?? null,
                'sfp_thresholds' => $domData['thresholds'] ?? null,
                'sfp_alarms' => $domData['alarms'] ?? null,
                'sfp_warnings' => $domData['warnings'] ?? null,

                // Counters
                'if_in_octets' => $inOctets,
                'if_out_octets' => $outOctets,
                'if_in_errors' => $inErrors,
                'if_out_errors' => $outErrors,
                'if_in_discards' => $inDiscards,
                'if_out_discards' => $outDiscards,
                'if_in_ucast_pkts' => $inUcastPkts,
                'if_out_ucast_pkts' => $outUcastPkts,

                // Uptime
                'if_last_change' => $lastChange,
                'link_up_since' => $this->calculateLinkUpSince($lastChange, $operStatus),
                'uptime_string' => $this->formatUptime($lastChange, $operStatus),
            ];

            // Preserve manual classification flags
            if (isset($existingPorts[$ifIndex])) {
                $existing = $existingPorts[$ifIndex];
                $attributes['is_uplink'] = $existing->is_uplink;
                $attributes['is_pon'] = $existing->is_pon;
            } else {
                // Set classification flags based on type_label for new records
                $attributes['is_pon'] = $typeLabel === 'pon';
                $attributes['is_uplink'] = $typeLabel === 'uplink';
            }

            if (isset($existingPorts[$ifIndex])) {
                $existingPorts[$ifIndex]->update($attributes);
                $updated++;
            } else {
                OltPort::create($attributes);
                $created++;
            }
            $polled++;
        }

        return [
            'polled' => $polled,
            'created' => $created,
            'updated' => $updated,
            'reachable' => true,
        ];
    }

    protected function determineTypeLabel(string $name, string $alias, ?int $ifType, ?int $highSpeed): string
    {
        $nameLower = strtolower($name);
        $aliasLower = strtolower($alias);

        // Check for PON ports
        if (preg_match('/(pon|gpon|epon|xgs|xgpon)/i', $nameLower) || preg_match('/(pon|gpon|epon|xgs|xgpon)/i', $aliasLower)) {
            return 'pon';
        }

        // Check for management
        if (preg_match('/(mgmt|management|vlan|loopback)/i', $nameLower) || preg_match('/(mgmt|management|vlan|loopback)/i', $aliasLower)) {
            return 'mgmt';
        }

        // Check for uplink (high speed, typically 1G/10G/25G/100G, not PON)
        if ($highSpeed && $highSpeed >= 1000) {
            // Could be uplink or just high-speed access
            if (preg_match('/(uplink|uplink|trunk|core|backbone|aggregat)/i', $nameLower) || preg_match('/(uplink|uplink|trunk|core|backbone|aggregat)/i', $aliasLower)) {
                return 'uplink';
            }
            // Default high-speed ports to uplink if not PON
            return 'uplink';
        }

        // Check for SFP/SFP+ ports that might be uplinks
        if (preg_match('/(sfp|sfp\+|sfp28|qsfp|qsfp\+|qsfp28|qsfp56)/i', $nameLower) || preg_match('/(sfp|sfp\+|sfp28|qsfp|qsfp\+|qsfp28|qsfp56)/i', $aliasLower)) {
            return 'uplink';
        }

        // Gigabit ethernet - could be uplink or access
        if (preg_match('/(ge|gigabit|ethernet)/i', $nameLower) && $highSpeed && $highSpeed >= 1000) {
            return 'uplink';
        }

        return 'access';
    }

    protected function findSfpEntityInfo(int $ifIndex, array $entDescr, array $entVendorType, array $entName, array $entHwRev, array $entFwRev, array $entSerialNum, array $entMfgName, array $entModelName, array $entMfgDate, array $entClass): array
    {
        // ENTITY-MIB doesn't directly map to ifIndex. We need to find the physical entity
        // that corresponds to this interface. This is vendor-specific.
        // For now, return empty - vendor drivers should override getSfpDomData()
        return [];
    }

    protected function calculateLinkUpSince(?int $lastChange, ?int $operStatus): ?string
    {
        if (!$lastChange || $operStatus !== 1) {
            return null;
        }
        // lastChange is in timeticks (1/100 seconds) since sysUpTime
        // We'd need sysUpTime to calculate absolute time
        // For now, return null - can be enhanced with sysUpTime polling
        return null;
    }

    protected function formatUptime(?int $lastChange, ?int $operStatus): ?string
    {
        if (!$lastChange || $operStatus !== 1) {
            return null;
        }
        // Convert timeticks to human readable
        $seconds = $lastChange / 100;
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days) {
            $parts[] = "{$days}d";
        }
        if ($hours) {
            $parts[] = "{$hours}h";
        }
        if ($minutes) {
            $parts[] = "{$minutes}m";
        }
        if (empty($parts)) {
            $parts[] = "<1m";
        }

        return implode(' ', $parts);
    }

    protected function formatMacAddress(string $mac): string
    {
        foreach (['= Hex-STRING: ', 'Hex-STRING: ', '=HEX-STRING: ', 'HEX-STRING: ', '= ', '='] as $prefix) {
            if (str_starts_with($mac, $prefix)) {
                $mac = substr($mac, strlen($prefix));
                break;
            }
        }
        $mac = str_replace(' ', ':', strtoupper(trim($mac)));
        // Validate MAC format
        if (preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            return $mac;
        }
        return '';
    }

    protected function normalizeOid(string $oid): string
    {
        $replacements = [
            'SNMPv2-SMI::enterprises' => '1.3.6.1.4.1',
            'enterprises' => '1.3.6.1.4.1',
            'SNMPv2-SMI::' => '',
        ];

        foreach ($replacements as $symbolic => $numeric) {
            if (str_contains($oid, $symbolic)) {
                $oid = str_replace($symbolic, $numeric, $oid);
                break;
            }
        }

        if (strpos($oid, '.') !== 0) {
            $oid = '.' . $oid;
        }

        return $oid;
    }
}
