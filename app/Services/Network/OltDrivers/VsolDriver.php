<?php

namespace App\Services\Network\OltDrivers;

use App\Models\NetworkDevice;
use App\Models\Olt;
use App\Services\Network\SnmpService;

class VsolDriver implements OltDriverInterface
{
    protected SnmpService $snmp;
    protected NetworkDevice $device;

    // VSOL OIDs for V1600D - Updated from vsol_v1600d_snmp.txt
    // Enterprise OID: .1.3.6.1.4.1.37950

    // NOTE: VSOL V1600D does NOT expose per-ONU optical power via SNMP.
    // The OID .1.3.6.1.4.1.37950.1.1.5.10.9.4.2.1.4 is SFP Tx power, NOT ONU Rx power.
    // Per-ONU optical power tables (5.10.13.3.3.1.3/1.4/1.5) return zeros/NULL.
    // Per-PON-port optical power IS available at 5.10.13.1.1.2 (Rx) and 1.1.4 (Tx).

    // Per-PON-port optical power OIDs (indexed by PON port 1-4)
    // Rx Power: .1.3.6.1.4.1.37950.1.1.5.10.13.1.1.2.{pon_port} (values like 53.87 = -26.13 dBm? or raw?)
    // Tx Power: .1.3.6.1.4.1.37950.1.1.5.10.13.1.1.4.{pon_port}
    // Voltage:  .1.3.6.1.4.1.37950.1.1.5.10.13.1.1.3.{pon_port}
    // Temp:     .1.3.6.1.4.1.37950.1.1.5.10.13.1.1.5.{pon_port}
    public const OID_PON_RX_POWER = '.1.3.6.1.4.1.37950.1.1.5.10.13.1.1.2';
    public const OID_PON_TX_POWER = '.1.3.6.1.4.1.37950.1.1.5.10.13.1.1.4';
    public const OID_PON_VOLTAGE  = '.1.3.6.1.4.1.37950.1.1.5.10.13.1.1.3';
    public const OID_PON_TEMP     = '.1.3.6.1.4.1.37950.1.1.5.10.13.1.1.5';

    // ONU discovery table OIDs
    // ONU Description table: Contains strings like "PON 0/1 ONU 8 80:66:29:0C:1E:5D."
    public const OID_ONU_DESCRIPTION = '.1.3.6.1.4.1.37950.1.1.5.10.13.2.10';

    // ONU description with more details (indexed by ONU): .1.3.6.1.4.1.37950.1.1.5.10.13.2.10.N
    // This provides: serial number, MAC address, PON port, status, registration time, description
    public const OID_ONU_DESC_TABLE = '.1.3.6.1.4.1.37950.1.1.5.10.13.2';
    public const OID_ONU_DESC_TYPE = '.1.3.6.1.4.1.37950.1.1.5.10.13.2.1';  // ONU type/status (0=offline, 1=online, 2=registered, 8=finishing)
    public const OID_ONU_DESC_SLOT = '.1.3.6.1.4.1.37950.1.1.5.10.13.2.2';   // Slot number
    public const OID_ONU_DESC_PORT = '.1.3.6.1.4.1.37950.1.1.5.10.13.2.3';   // Port number
    public const OID_ONU_DESC_INDEX = '.1.3.6.1.4.1.37950.1.1.5.10.13.2.4';  // Index
    public const OID_ONU_DESC_OID = '.1.3.6.1.4.1.37950.1.1.5.10.13.2.5';   // OID reference
    public const OID_ONU_DESC_COUNT = '.1.3.6.1.4.1.37950.1.1.5.10.13.2.6';  // Count
    public const OID_ONU_DESC_MAC = '.1.3.6.1.4.1.37950.1.1.5.10.13.2.7';   // MAC address (Hex-STRING)
    public const OID_ONU_DESC_TIME = '.1.3.6.1.4.1.37950.1.1.5.10.13.2.8';  // Registration time
    public const OID_ONU_DESC_FULL = '.1.3.6.1.4.1.37950.1.1.5.10.13.2.10'; // Full description with serial

    // Legacy ONU table OIDs (may not exist on all VSOL devices)
    public const OID_ONU_TABLE = '.1.3.6.1.4.1.37950.1.1.5.10.3.2.1';
    public const OID_ONU_SERIAL = '.1.3.6.1.4.1.37950.1.1.5.10.3.2.1.1';
    public const OID_ONU_MAC = '.1.3.6.1.4.1.37950.1.1.5.10.3.2.1.3';
    public const OID_ONU_PON_PORT = '.1.3.6.1.4.1.37950.1.1.5.10.3.2.1.2';
    public const OID_ONU_STATUS = '.1.3.6.1.4.1.37950.1.1.5.10.3.2.1.4';
    public const OID_ONU_INTERFACE = '.1.3.6.1.4.1.37950.1.1.5.10.3.2.1.5';

    // IF-MIB OIDs for interface descriptions
    public const OID_IF_ALIAS = '.1.3.6.1.2.1.31.1.1.1.18';
    public const OID_IF_DESCR = '.1.3.6.1.2.1.2.2.1.2';

    public function __construct(Olt $olt)
    {
        $this->device = $olt->networkDevice;
        $this->snmp = new SnmpService(
            $this->device->ip_address,
            $this->device->snmp_community ?? 'public',
            $this->device->snmp_version ?? '2c',
            $this->device->snmp_port ?? 161
        );
    }

    public function getOnuRxPower(string $ponPort, string $onuMacOrId): ?float
    {
        // VSOL V1600D does NOT expose per-ONU optical power via SNMP.
        // The per-ONU optical power tables (5.10.13.3.3.1.3/1.4/1.5) return zeros/NULL.
        // The OID previously used (5.10.9.4.2.1.4) is actually SFP Tx power, not ONU Rx power.
        // Return null to indicate unavailable - the sync service will skip signal update.
        return null;
    }

    /**
     * Get per-PON-port optical power (not per-ONU).
     * VSOL V1600D exposes PON port level optical power at 5.10.13.1.1.2 (Rx) and 1.1.4 (Tx).
     * Returns array indexed by PON port number (1-4).
     */
    public function getPonPortOpticalPower(): array
    {
        $rxPowerRows = $this->snmp->walk(self::OID_PON_RX_POWER);
        $txPowerRows = $this->snmp->walk(self::OID_PON_TX_POWER);
        $voltageRows = $this->snmp->walk(self::OID_PON_VOLTAGE);
        $tempRows = $this->snmp->walk(self::OID_PON_TEMP);

        $rxByPort = $this->normalizeWalkToIndex($rxPowerRows);
        $txByPort = $this->normalizeWalkToIndex($txPowerRows);
        $voltageByPort = $this->normalizeWalkToIndex($voltageRows);
        $tempByPort = $this->normalizeWalkToIndex($tempRows);

        $allPorts = array_unique(array_merge(
            array_keys($rxByPort),
            array_keys($txByPort),
            array_keys($voltageByPort),
            array_keys($tempByPort)
        ));

        $result = [];
        foreach ($allPorts as $port) {
            $result[$port] = [
                'rx_power_dbm' => $this->parsePonOpticalValue($rxByPort[$port] ?? null),
                'tx_power_dbm' => $this->parsePonOpticalValue($txByPort[$port] ?? null),
                'voltage_v' => $this->parsePonOpticalValue($voltageByPort[$port] ?? null, 100),
                'temperature_c' => $this->parsePonOpticalValue($tempByPort[$port] ?? null, 10),
            ];
        }
        return $result;
    }

    /**
     * Parse PON port optical values.
     * Observed raw values:
     *   Rx: 53.87, 35.09, 36.84, 34.04 (vendor-specific encoding, not standard dBm)
     *   Tx: 31.40, 11.75, 23.15, 6.60 (vendor-specific encoding)
     *   Voltage: 3.21, 3.30, 3.27, 3.29 (volts * 100)
     *   Temp: 10.21, 10.47, 9.21, 10.53 (Celsius, already decimal)
     */
    protected function parsePonOpticalValue(?string $val, int $divisor = 100): ?float
    {
        if ($val === null || $val === '') {
            return null;
        }
        $val = trim(preg_replace('/^(INTEGER|Gauge32|STRING):\s*/i', '', $val));
        if (preg_match('/(-?\d+(?:\.\d+)?)/', $val, $matches)) {
            $num = (float) $matches[1];
            // Voltage is * 100 (3.21 -> 3.21V after /100)
            // Temperature is already in Celsius (10.21 -> 10.21°C)
            // Rx/Tx power are vendor-specific encoding - return raw for now
            if ($divisor > 1 && abs($num) > $divisor) {
                return round($num / $divisor, 2);
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

    public function discoverOnus(): array
    {
        $indexed = [];

        // PRIMARY: Walk ifDescr (standard IF-MIB) to get all ONUs with customer names
        // Pattern: "EPON01ONU1 Joshim_shop", "EPON02ONU5 Elias", etc.
        // This provides customer names and maps to PON port + ONU ID for V1600D
        $ifDescrs = $this->snmp->walk(self::OID_IF_DESCR);
        foreach ($ifDescrs as $ifOid => $descr) {
            if (preg_match('/\.(\d+)$/', $this->normalizeOid($ifOid), $m)) {
                $ifIndex = $m[1];
                $descr = trim($descr, ' ."');

                // Parse EPON{port}ONU{id} pattern
                if (preg_match('/^EPON(\d+)ONU(\d+)\s+(.+)$/', $descr, $matches)) {
                    $ponPort = (int) $matches[1];
                    $onuId = (int) $matches[2];
                    $customerName = trim($matches[3]);

                    $indexed[$ifIndex] = [
                        'customer_name' => $customerName,
                        'pon_port' => $ponPort,
                        'pon_port_name' => "PON {$ponPort}",
                        'onu_id' => $onuId,
                        'ONU_type' => $descr,
                        'is_registered' => true,
                        'if_index' => $ifIndex,
                    ];
                }
            }
        }

        // SECONDARY: Walk VSOL-specific MAC OID to get MAC addresses
        // These may use a different indexing scheme, so match by MAC later
        $vsolMacRows = $this->snmp->walk(self::OID_ONU_DESC_MAC);
        $vsolMacs = [];
        foreach ($vsolMacRows as $oid => $value) {
            $normalizedOid = $this->normalizeOid($oid);
            if (preg_match('/\.(\d+)$/', $normalizedOid, $m)) {
                $index = $m[1];
                $vsolMacs[$index] = $this->formatMacAddress($value);
            }
        }

        // Also walk legacy MAC OID as fallback
        $legacyMacRows = $this->snmp->walk(self::OID_ONU_MAC);
        foreach ($legacyMacRows as $oid => $value) {
            $normalizedOid = $this->normalizeOid($oid);
            if (preg_match('/\.(\d+)$/', $normalizedOid, $m)) {
                $index = $m[1];
                if (!isset($vsolMacs[$index])) {
                    $vsolMacs[$index] = $this->formatMacAddress($value);
                }
            }
        }

        // Walk VSOL description OID for serial numbers and detailed info
        $vsolDescRows = $this->snmp->walk(self::OID_ONU_DESC_FULL);
        $vsolDescs = [];
        foreach ($vsolDescRows as $oid => $value) {
            $normalizedOid = $this->normalizeOid($oid);
            if (preg_match('/\.(\d+)$/', $normalizedOid, $m)) {
                $index = $m[1];
                $vsolDescs[$index] = trim($value, ' ."');
            }
        }

        // Walk VSOL description OID (alternative)
        $vsolDescRows2 = $this->snmp->walk(self::OID_ONU_DESCRIPTION);
        foreach ($vsolDescRows2 as $oid => $value) {
            $normalizedOid = $this->normalizeOid($oid);
            if (preg_match('/\.(\d+)$/', $normalizedOid, $m)) {
                $index = $m[1];
                if (!isset($vsolDescs[$index])) {
                    $vsolDescs[$index] = trim($value, ' ."');
                }
            }
        }

        // Walk legacy ONU table for additional data
        $legacySerialRows = $this->snmp->walk(self::OID_ONU_SERIAL);
        $legacyPonRows = $this->snmp->walk(self::OID_ONU_PON_PORT);
        $legacyStatusRows = $this->snmp->walk(self::OID_ONU_STATUS);
        $legacyInterfaceRows = $this->snmp->walk(self::OID_ONU_INTERFACE);

        $legacySerials = [];
        foreach ($legacySerialRows as $oid => $value) {
            $normalizedOid = $this->normalizeOid($oid);
            if (preg_match('/\.(\d+)$/', $normalizedOid, $m)) {
                $legacySerials[$m[1]] = $value;
            }
        }
        $legacyPons = [];
        foreach ($legacyPonRows as $oid => $value) {
            $normalizedOid = $this->normalizeOid($oid);
            if (preg_match('/\.(\d+)$/', $normalizedOid, $m)) {
                $legacyPons[$m[1]] = is_numeric($value) ? (int) $value : null;
            }
        }
        $legacyStatus = [];
        foreach ($legacyStatusRows as $oid => $value) {
            $normalizedOid = $this->normalizeOid($oid);
            if (preg_match('/\.(\d+)$/', $normalizedOid, $m)) {
                $legacyStatus[$m[1]] = $value !== '0' && $value !== '';
            }
        }
        $legacyInterfaces = [];
        foreach ($legacyInterfaceRows as $oid => $value) {
            $normalizedOid = $this->normalizeOid($oid);
            if (preg_match('/\.(\d+)$/', $normalizedOid, $m)) {
                $legacyInterfaces[$m[1]] = $value !== '' ? $value : null;
            }
        }

        // Walk ifAlias for additional descriptions
        $ifAliases = $this->snmp->walk(self::OID_IF_ALIAS);
        foreach ($ifAliases as $ifOid => $alias) {
            if (preg_match('/\.(\d+)$/', $this->normalizeOid($ifOid), $m)) {
                $ifIndex = $m[1];
                if (isset($indexed[$ifIndex]) && !empty($alias)) {
                    $alias = trim($alias, '"');
                    if (empty($indexed[$ifIndex]['ONU_type']) ||
                        (strpos($alias, 'ONU') !== false && strpos($indexed[$ifIndex]['ONU_type'], 'ONU') === false)) {
                        $indexed[$ifIndex]['ONU_type'] = $alias;
                    }
                }
            }
        }

        // Now enrich ifDescr entries with MAC addresses and serial numbers
        // Parse VSOL descriptions to extract MAC addresses and match to ifDescr entries
        foreach ($vsolDescs as $vsolIndex => $desc) {
            $onuData = $this->parseVsolOnuDescription($desc);
            if (!empty($onuData['mac'])) {
                $mac = $onuData['mac'];
                // Find matching ifDescr entry by PON port and ONU ID
                foreach ($indexed as $ifIndex => $data) {
                    if (($data['pon_port'] ?? null) === ($onuData['pon_port'] ?? null) &&
                        ($data['onu_id'] ?? null) === ($onuData['onu_id'] ?? null)) {
                        $indexed[$ifIndex]['mac_address'] = $mac;
                        $indexed[$ifIndex]['serial_number'] = $onuData['serial'] ?? $mac;
                        break;
                    }
                }
            }
        }

        // Also try to match VSOL MACs by index if they align
        foreach ($vsolMacs as $vsolIndex => $mac) {
            // Try direct index match first
            if (isset($indexed[$vsolIndex])) {
                if (empty($indexed[$vsolIndex]['mac_address'])) {
                    $indexed[$vsolIndex]['mac_address'] = $mac;
                }
                if (empty($indexed[$vsolIndex]['serial_number'])) {
                    $indexed[$vsolIndex]['serial_number'] = $mac;
                }
            }
        }

        // Match legacy data by index
        foreach ($legacySerials as $idx => $serial) {
            if (isset($indexed[$idx])) {
                if (empty($indexed[$idx]['serial_number'])) {
                    $indexed[$idx]['serial_number'] = $serial;
                }
            }
        }
        foreach ($legacyPons as $idx => $pon) {
            if (isset($indexed[$idx]) && empty($indexed[$idx]['pon_port'])) {
                $indexed[$idx]['pon_port'] = $pon;
                $indexed[$idx]['pon_port_name'] = "PON {$pon}";
            }
        }
        foreach ($legacyStatus as $idx => $status) {
            if (isset($indexed[$idx])) {
                $indexed[$idx]['is_registered'] = $status;
            }
        }
        foreach ($legacyInterfaces as $idx => $interface) {
            if (isset($indexed[$idx]) && empty($indexed[$idx]['ONU_type'])) {
                $indexed[$idx]['ONU_type'] = $interface;
            }
        }

        // For any ifDescr entries still missing MAC, try to get from VSOL descriptions by PON/ONU
        foreach ($indexed as $ifIndex => &$data) {
            if (empty($data['mac_address']) && !empty($data['pon_port']) && !empty($data['onu_id'])) {
                // Try to find MAC in VSOL descriptions by PON/ONU
                foreach ($vsolDescs as $vsolIndex => $desc) {
                    $onuData = $this->parseVsolOnuDescription($desc);
                    if (($onuData['pon_port'] ?? null) === $data['pon_port'] &&
                        ($onuData['onu_id'] ?? null) === $data['onu_id'] &&
                        !empty($onuData['mac'])) {
                        $data['mac_address'] = $onuData['mac'];
                        $data['serial_number'] = $onuData['serial'] ?? $onuData['mac'];
                        break;
                    }
                }
            }
        }

        // If still no MAC but we have VSOL MACs, try to distribute them
        // This is a fallback for when indexing doesn't align
        $unusedVsolMacs = array_values(array_filter($vsolMacs, function ($mac, $idx) use ($indexed) {
            // Check if this MAC is already used
            foreach ($indexed as $data) {
                if (($data['mac_address'] ?? null) === $mac) {
                    return false;
                }
            }
            return true;
        }, ARRAY_FILTER_USE_BOTH));

        foreach ($indexed as $ifIndex => &$data) {
            if (empty($data['mac_address']) && !empty($unusedVsolMacs)) {
                $data['mac_address'] = array_shift($unusedVsolMacs);
                $data['serial_number'] = $data['mac_address'];
            }
        }

        // Build final ONU list
        $onus = [];
        foreach ($indexed as $index => $data) {
            // Some devices don't have serial numbers, use MAC as identifier
            if (empty($data['serial_number']) && empty($data['mac_address'])) {
                continue;
            }
            // If no serial but has MAC, use MAC as the serial for matching
            if (empty($data['serial_number']) && !empty($data['mac_address'])) {
                $data['serial_number'] = $data['mac_address'];
            }
            $onus[] = [
                'serial_number' => $data['serial_number'],
                'mac_address' => $data['mac_address'] ?? null,
                'pon_port' => $data['pon_port'] ?? null,
                'pon_port_name' => $data['pon_port_name'] ?? ($data['pon_port'] !== null ? "PON {$data['pon_port']}" : null),
                'ONU_id' => $data['onu_id'] ?? $index,
                'is_registered' => $data['is_registered'] ?? true,
                'registered_at' => null,
                'vendor_id' => null,
                'firmware_version' => null,
                'hardware_version' => null,
                'ONU_type' => $data['ONU_type'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
            ];
        }

        return $onus;
    }

    /**
     * Parse VSOL ONU description string to extract serial, MAC, and PON port.
     * Example: "PON 0/1 ONU 8 80:66:29:0C:1E:5D."
     */
    protected function parseVsolOnuDescription(string $description): array
    {
        $result = [
            'pon_port' => null,
            'serial' => null,
            'mac' => null,
        ];

        // Pattern: "PON X/Y ONU N SERIAL MAC"
        // or: "PON X/Y ONU N MAC"
        if (preg_match('/PON\s+(\d+)\/(\d+)\s+ONU\s+(\d+)\s+([0-9A-Fa-f:]+)/', $description, $matches)) {
            $result['pon_port'] = (int) $matches[1];
            $result['serial'] = $matches[4];
            $result['mac'] = $this->formatMacAddress($matches[4]);
        } elseif (preg_match('/ONU\s+(\d+)\s+([0-9A-Fa-f:]+)/', $description, $matches)) {
            $result['serial'] = $matches[2];
            $result['mac'] = $this->formatMacAddress($matches[2]);
        }

        // Also try to extract MAC from Hex-STRING format
        if (empty($result['mac']) && preg_match('/([0-9A-Fa-f]{12})/', $description, $matches)) {
            $result['mac'] = $this->formatMacAddress($matches[1]);
        }

        return $result;
    }

    /**
     * Format Hex-STRING MAC address to standard format.
     * Converts "00 3A 7D 96 7F 0A" to "00:3A:7D:96:7F:0A"
     */
    protected function formatMacAddress(string $mac): string
    {
        // Remove various prefixes that might be present
        foreach (['= Hex-STRING: ', 'Hex-STRING: ', '=HEX-STRING: ', 'HEX-STRING: ', '= ', '='] as $prefix) {
            if (str_starts_with($mac, $prefix)) {
                $mac = substr($mac, strlen($prefix));
                break;
            }
        }
        // Convert space-separated hex pairs to colon-separated
        return str_replace(' ', ':', strtoupper(trim($mac)));
    }

    protected function normalizeOid(string $oid): string
    {
        // Replace common symbolic prefixes with numeric equivalents
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

        // Ensure it starts with a dot
        if (strpos($oid, '.') !== 0) {
            $oid = '.' . $oid;
        }

        return $oid;
    }

    protected function resolveOnuIndex(string $ponPort, string $onuMacOrId): ?string
    {
        // If it's already a numeric index, use it directly
        if (is_numeric($onuMacOrId)) {
            return $onuMacOrId;
        }

        // Try to find the ONU by MAC address using VSOL description MAC OID
        // This uses the VSOL description table indexing (slot/port/onu_index)
        $normalizedMac = strtoupper(str_replace([':', '-', ' '], '', $onuMacOrId));

        $macRows = $this->snmp->walk(self::OID_ONU_DESC_MAC);

        foreach ($macRows as $oid => $value) {
            $formattedMac = $this->formatMacAddress($value);
            $macWithoutSeparators = strtoupper(str_replace(':', '', $formattedMac));

            if ($macWithoutSeparators === $normalizedMac) {
                // Extract the index from the OID (this is the VSOL description table index)
                if (preg_match('/\.(\d+)$/', $this->normalizeOid($oid), $m)) {
                    return $m[1];
                }
            }
        }

        // Fallback: try legacy MAC OID
        $legacyMacRows = $this->snmp->walk(self::OID_ONU_MAC);
        foreach ($legacyMacRows as $oid => $value) {
            $formattedMac = $this->formatMacAddress($value);
            $macWithoutSeparators = strtoupper(str_replace(':', '', $formattedMac));

            if ($macWithoutSeparators === $normalizedMac) {
                if (preg_match('/\.(\d+)$/', $this->normalizeOid($oid), $m)) {
                    return $m[1];
                }
            }
        }

        // If MAC lookup fails, try to resolve by PON port and ONU ID using VSOL description table
        // Walk slot, port, and index OIDs to find matching entry
        if (is_numeric($ponPort)) {
            $ponPortNum = (int) $ponPort;
            $onuIdNum = is_numeric($onuMacOrId) ? (int) $onuMacOrId : null;

            $slotRows = $this->snmp->walk(self::OID_ONU_DESC_SLOT);
            $portRows = $this->snmp->walk(self::OID_ONU_DESC_PORT);
            $indexRows = $this->snmp->walk(self::OID_ONU_DESC_INDEX);

            $slots = [];
            foreach ($slotRows as $oid => $value) {
                if (preg_match('/\.(\d+)$/', $this->normalizeOid($oid), $m)) {
                    $slots[$m[1]] = is_numeric($value) ? (int) $value : null;
                }
            }
            $ports = [];
            foreach ($portRows as $oid => $value) {
                if (preg_match('/\.(\d+)$/', $this->normalizeOid($oid), $m)) {
                    $ports[$m[1]] = is_numeric($value) ? (int) $value : null;
                }
            }
            $indices = [];
            foreach ($indexRows as $oid => $value) {
                if (preg_match('/\.(\d+)$/', $this->normalizeOid($oid), $m)) {
                    $indices[$m[1]] = is_numeric($value) ? (int) $value : null;
                }
            }

            // Find index where slot matches (usually 0 or 1 for V1600D), port matches PON, and index matches ONU_id
            foreach ($slots as $idx => $slot) {
                // V1600D typically uses slot 0 or 1
                if (($ports[$idx] ?? null) === $ponPortNum && ($indices[$idx] ?? null) === $onuIdNum) {
                    return (string) $idx;
                }
            }
        }

        return null;
    }

    public function getSnmpService(): SnmpService
    {
        return $this->snmp;
    }

    public function getSfpDomData(): array
    {
        // VSOL V1600D SFP DOM OIDs (vendor-specific)
        // Based on VSOL MIB: .1.3.6.1.4.1.37950.1.1.5.10.9
        // SFP Tx Power: .1.3.6.1.4.1.37950.1.1.5.10.9.4.2.1.4 (same as ONU Rx but for SFP)
        // SFP Rx Power: .1.3.6.1.4.1.37950.1.1.5.10.9.4.2.1.5
        // SFP Temperature: .1.3.6.1.4.1.37950.1.1.5.10.9.4.2.1.6
        // SFP Voltage: .1.3.6.1.4.1.37950.1.1.5.10.9.4.2.1.7
        // SFP Tx Bias: .1.3.6.1.4.1.37950.1.1.5.10.9.4.2.1.8
        // SFP Vendor/Part/Serial: ENTITY-MIB or vendor-specific

        $domData = [];

        // VSOL SFP DOM OIDs
        $sfpTxPowerOid = '.1.3.6.1.4.1.37950.1.1.5.10.9.4.2.1.4';
        $sfpRxPowerOid = '.1.3.6.1.4.1.37950.1.1.5.10.9.4.2.1.5';
        $sfpTempOid = '.1.3.6.1.4.1.37950.1.1.5.10.9.4.2.1.6';
        $sfpVoltageOid = '.1.3.6.1.4.1.37950.1.1.5.10.9.4.2.1.7';
        $sfpTxBiasOid = '.1.3.6.1.4.1.37950.1.1.5.10.9.4.2.1.8';

        $txPowerRows = $this->snmp->walk($sfpTxPowerOid);
        $rxPowerRows = $this->snmp->walk($sfpRxPowerOid);
        $tempRows = $this->snmp->walk($sfpTempOid);
        $voltageRows = $this->snmp->walk($sfpVoltageOid);
        $txBiasRows = $this->snmp->walk($sfpTxBiasOid);

        // Normalize all walk results to index => value maps
        $txPowerByIndex = $this->normalizeWalkToIndex($txPowerRows);
        $rxPowerByIndex = $this->normalizeWalkToIndex($rxPowerRows);
        $tempByIndex = $this->normalizeWalkToIndex($tempRows);
        $voltageByIndex = $this->normalizeWalkToIndex($voltageRows);
        $txBiasByIndex = $this->normalizeWalkToIndex($txBiasRows);

        $allIndices = array_unique(array_merge(
            array_keys($txPowerByIndex),
            array_keys($rxPowerByIndex),
            array_keys($tempByIndex),
            array_keys($voltageByIndex),
            array_keys($txBiasByIndex)
        ));

        foreach ($allIndices as $index) {
            $domData[$index] = [
                'tx_power_dbm' => $this->parseVsolDomValue($txPowerByIndex[$index] ?? null, 10000),
                'rx_power_dbm' => $this->parseVsolDomValue($rxPowerByIndex[$index] ?? null, 100),
                'temperature_c' => $this->parseVsolDomValue($tempByIndex[$index] ?? null, 10),
                'voltage_v' => $this->parseVsolDomValue($voltageByIndex[$index] ?? null, 1000),
                'tx_bias_ma' => $this->parseVsolDomValue($txBiasByIndex[$index] ?? null, 100),
            ];
        }

        return $domData;
    }

    /**
     * Convert SNMP walk result (symbolic OID => value) to index => value map.
     */
    protected function normalizeWalkToIndex(array $walkResult): array
    {
        $byIndex = [];
        foreach ($walkResult as $oid => $value) {
            $normalizedOid = $this->normalizeOid($oid);
            if (preg_match('/\.(\d+)$/', $normalizedOid, $m)) {
                $index = (int) $m[1];
                $byIndex[$index] = $value;
            }
        }
        return $byIndex;
    }

    protected function parseVsolDomValue(?string $val, int $divisor = 100): ?float
    {
        if ($val === null || $val === '') {
            return null;
        }
        $val = trim(preg_replace('/^(INTEGER|Gauge32|STRING):\s*/i', '', $val));
        if (preg_match('/(-?\d+)/', $val, $matches)) {
            $num = (float) $matches[1];

            // VSOL SFP DOM encoding (observed from V1600D):
            // - Tx Power: 200000 = 20.00 dBm (divide by 10000)
            // - Rx Power: 128 = -12.8 dBm (divide by 10 and negate, or special encoding)
            // - Temperature: 1 = 10°C (multiply by 10)
            // - Voltage: typically in mV (divide by 1000)
            // - Tx Bias: typically in µA (divide by 100)

            // Handle large positive Tx power values (200000 -> 20.00 dBm)
            if ($divisor === 100 && $num > 10000) {
                return round($num / 10000, 2);
            }

            // Handle Rx power - VSOL often returns positive values that need negation
            // 128 -> -12.8 dBm (divide by 10 and negate)
            if ($divisor === 100 && $num > 0 && $num < 1000) {
                return round(-($num / 10), 2);
            }

            // Standard VSOL format: multiplied by divisor (e.g., -2543 = -25.43 dBm)
            if (abs($num) > $divisor) {
                return round($num / $divisor, 2);
            }

            // Temperature: VSOL returns value * 10 (1 -> 10°C)
            if ($divisor === 10 && $num > 0 && $num < 100) {
                return round($num * 10, 1);
            }

            // Already in correct units
            return round($num, 2);
        }
        return null;
    }
}
