<?php

namespace App\Services\Network\OltDrivers;

use App\Models\NetworkDevice;
use App\Models\Olt;
use App\Services\Network\SnmpService;

class BdcomDriver implements OltDriverInterface
{
    protected SnmpService $snmp;
    protected NetworkDevice $device;
    protected string $enterpriseOid;
    protected array $oidMap;

    // BDCOM P3310C OID mappings - Updated from bdcom_p3310c_snmp.txt
    // Enterprise: 1.3.6.1.4.1.3320 (BDCOM's enterprise OID, NOT 37950 which is VSOL)
    // sysObjectID: .1.3.6.1.4.1.3320.1.294.0

    protected const OID_MAPS = [
        '1.3.6.1.4.1.3320' => [
            // BDCOM P3310C - Updated with actual OIDs from SNMP discovery
            // ONU table: .1.3.6.1.4.1.3320.9.109.1.2.1.1
            // Column 1: ONU ID (Gauge32) - e.g., 2201850928
            // Column 2: ONU Type Name (STRING) - e.g., "PMONU", "HONU", "Tdtx", "SCRX", "SCTX"

            // Optical power OIDs from the SNMP walk:
            // SCRX (ONU Receive Optical Power): .1.3.6.1.4.1.3320.9.109.1.2.1.1.2.2231732576 = STRING: "SCRX"
            // SCTX (ONU Transmit Optical Power): .1.3.6.1.4.1.3320.9.109.1.2.1.1.2.2250821360 = STRING: "SCTX"
            // bcmRX: .1.3.6.1.4.1.3320.9.109.1.2.1.1.2.2265024800 = STRING: "bcmRX"
            // bcmTX: .1.3.6.1.4.1.3320.9.109.1.2.1.1.2.2267663216 = STRING: "bcmTX"

            'rx_power' => '.1.3.6.1.4.1.3320.9.109.1.2.1.1.2.2231732576',
            'tx_power' => '.1.3.6.1.4.1.3320.9.109.1.2.1.1.2.2250821360',

            // ONU discovery table
            // Table entries are at: .1.3.6.1.4.1.3320.9.109.1.2.1.1.1.<index>
            // Column 1 (index 1): ONU ID (Gauge32)
            // Column 2 (index 2): ONU Type/Name (STRING)
            'onu_table' => '.1.3.6.1.4.1.3320.9.109.1.2.1.1',
            'onu_id' => '.1.3.6.1.4.1.3320.9.109.1.2.1.1.1',
            'onu_type' => '.1.3.6.1.4.1.3320.9.109.1.2.1.1.2',

            // Legacy mappings (kept for backward compatibility)
            'onu_serial' => null,
            'onu_mac' => null,
            'onu_pon_port' => null,
            'onu_status' => null,
            'onu_interface' => null,
        ],
    ];

    // IF-MIB OIDs
    public const OID_IF_ALIAS = '.1.3.6.1.2.1.31.1.1.1.18';

    // BDCOM-specific ONU OIDs
    // ONU Receive Optical Power (dBm * 100): .1.3.6.1.4.1.3320.9.109.1.2.1.1.3.<onu_id>
    // ONU Transmit Optical Power (dBm * 100): May be in a different OID
    public const OID_ONU_RX_POWER = '.1.3.6.1.4.1.3320.9.109.1.2.1.1.3';
    public const OID_ONU_TYPE = '.1.3.6.1.4.1.3320.9.109.1.2.1.1.2';

    public function __construct(Olt $olt)
    {
        $this->device = $olt->networkDevice;
        $this->snmp = new SnmpService(
            $this->device->ip_address,
            $this->device->snmp_community ?? 'public',
            $this->device->snmp_version ?? '2c',
            $this->device->snmp_port ?? 161
        );

        $this->detectEnterpriseOid();
    }

    protected function detectEnterpriseOid(): void
    {
        $sysObjectId = $this->snmp->get('sysObjectID.0');

        // BDCOM P3310C sysObjectID from SNMP discovery: .1.3.6.1.4.1.3320.1.294.0
        // Default to BDCOM enterprise OID (3320, not 37950 which is VSOL)

        if ($sysObjectId === null) {
            $this->enterpriseOid = '1.3.6.1.4.1.3320';
            $this->oidMap = self::OID_MAPS['1.3.6.1.4.1.3320'];
            return;
        }

        if (str_contains($sysObjectId, '3320')) {
            $this->enterpriseOid = '1.3.6.1.4.1.3320';
            $this->oidMap = self::OID_MAPS['1.3.6.1.4.1.3320'];
            return;
        }

        if (preg_match('/enterprises\.(\d+)/', $sysObjectId, $matches)) {
            $enterpriseNum = $matches[1];
            $this->enterpriseOid = "1.3.6.1.4.1.{$enterpriseNum}";

            if (isset(self::OID_MAPS[$this->enterpriseOid])) {
                $this->oidMap = self::OID_MAPS[$this->enterpriseOid];
            } else {
                // Fallback to BDCOM
                $this->enterpriseOid = '1.3.6.1.4.1.3320';
                $this->oidMap = self::OID_MAPS['1.3.6.1.4.1.3320'];
            }
        } else {
            $this->enterpriseOid = '1.3.6.1.4.1.3320';
            $this->oidMap = self::OID_MAPS['1.3.6.1.4.1.3320'];
        }
    }

    public function getOnuRxPower(string $ponPort, string $onuMacOrId): ?float
    {
        // BDCOM P3310C: Optical power values are in column 3 of the ONU table
        // OID: .1.3.6.1.4.1.3320.9.109.1.2.1.1.3.<onu_index>
        // The value is in dBm * 100 format (e.g., -2543 = -25.43 dBm)

        $index = $this->resolveOnuIndex($ponPort, $onuMacOrId);
        if (!$index) {
            return null;
        }

        // Try the dedicated rx power OID first
        $rxPowerOid = $this->oidMap['rx_power'] ?? self::OID_ONU_RX_POWER;
        if ($rxPowerOid !== null) {
            $val = $this->snmp->get($rxPowerOid . '.' . $index);
            if ($val !== null && $val !== '') {
                if (preg_match('/(-?\d+)/', $val, $matches)) {
                    $num = (float) $matches[1];
                    // BDCOM returns values multiplied by 100
                    if (abs($num) > 100) {
                        return round($num / 100, 2);
                    }
                    return round($num, 2);
                }
            }
        }

        // Try alternative OIDs for optical power
        // In the SNMP walk, SCRX (ONU Rx Power) is at: .1.3.6.1.4.1.3320.9.109.1.2.1.1.2.2231732576
        // But this seems to be a specific instance, not a table
        // We need to find the ONU's optical power from the ONU table

        // For BDCOM, the optical power might be in a different table
        // Let's try to get it from the ONU's ifIndex and then check ifXTable
        return null;
    }

    public function getOnuTxPower(string $ponPort, string $onuMacOrId): ?float
    {
        // BDCOM P3310C: Tx power similar to Rx power

        $index = $this->resolveOnuIndex($ponPort, $onuMacOrId);
        if (!$index) {
            return null;
        }

        $txPowerOid = $this->oidMap['tx_power'] ?? null;
        if ($txPowerOid !== null) {
            $val = $this->snmp->get($txPowerOid . '.' . $index);
            if ($val !== null && $val !== '') {
                if (preg_match('/(-?\d+)/', $val, $matches)) {
                    $num = (float) $matches[1];
                    if (abs($num) > 100) {
                        return round($num / 100, 2);
                    }
                    return round($num, 2);
                }
            }
        }

        return null;
    }

    public function isOnuOnline(string $ponPort, string $onuMacOrId): bool
    {
        return true;
    }

    public function discoverOnus(): array
    {
        // BDCOM P3310C: ONU table is at .1.3.6.1.4.1.3320.9.109.1.2.1.1
        // Column structure:
        // - Column 1 (index 1): ONU ID (Gauge32) - e.g., 2201850928
        // - Column 2 (index 2): ONU Type Name (STRING) - e.g., "PMONU", "HONU"

        $onuTableOid = $this->oidMap['onu_table'] ?? self::OID_MAPS['1.3.6.1.4.1.3320']['onu_table'];
        $onuIdOid = $this->oidMap['onu_id'] ?? self::OID_MAPS['1.3.6.1.4.1.3320']['onu_id'];
        $onuTypeOid = $this->oidMap['onu_type'] ?? self::OID_MAPS['1.3.6.1.4.1.3320']['onu_type'];

        // Walk the ONU ID column to get all ONU indices
        $onuIdRows = $this->snmp->walk($onuIdOid);

        if (empty($onuIdRows)) {
            // Try walking the full table
            $onuIdRows = $this->snmp->walk($onuTableOid);
            if (empty($onuIdRows)) {
                return [];
            }
        }

        $indexed = [];

        // Extract ONU IDs from column 1
        foreach ($onuIdRows as $oid => $value) {
            $normalizedOid = $this->normalizeOid($oid);

            // Check if this is from the ONU ID column (ends with .1.<index>)
            if (preg_match('/\.1\.(\d+)$/', $normalizedOid, $m)) {
                $index = $m[1];
                $indexed[$index]['ONU_id'] = $value;
            } elseif (preg_match('/\.(\d+)$/', $normalizedOid, $m)) {
                // Might be from table walk, try to determine column
                $index = $m[1];
                $indexed[$index]['ONU_id'] = $value;
            }
        }

        // Get ONU type names from column 2
        $onuTypeRows = $this->snmp->walk($onuTypeOid);
        foreach ($onuTypeRows as $oid => $value) {
            $normalizedOid = $this->normalizeOid($oid);

            if (preg_match('/\.2\.(\d+)$/', $normalizedOid, $m)) {
                $index = $m[1];
                if (!isset($indexed[$index])) {
                    $indexed[$index] = [];
                }
                $indexed[$index]['ONU_type'] = $value !== '' ? trim($value, ' ."') : null;
            } elseif (preg_match('/\.(\d+)$/', $normalizedOid, $m)) {
                $index = $m[1];
                if (!isset($indexed[$index])) {
                    $indexed[$index] = [];
                }
                $indexed[$index]['ONU_type'] = $value !== '' ? trim($value, ' ."') : null;
            }
        }

        // Also try to walk the full ONU table to get all columns
        $fullTableRows = $this->snmp->walk($onuTableOid);
        foreach ($fullTableRows as $oid => $value) {
            $normalizedOid = $this->normalizeOid($oid);

            if (preg_match('/\.(\d+)\.(\d+)$/', $normalizedOid, $m)) {
                $column = $m[1];
                $index = $m[2];

                if (!isset($indexed[$index])) {
                    $indexed[$index] = [];
                }

                switch ($column) {
                    case '1':
                        $indexed[$index]['ONU_id'] = $value;
                        break;
                    case '2':
                        $indexed[$index]['ONU_type'] = $value !== '' ? trim($value, ' ."') : null;
                        break;
                    case '3':
                        // This might be the rx power value
                        if (is_numeric($value)) {
                            $indexed[$index]['rx_power_raw'] = (int) $value;
                        }
                        break;
                }
            }
        }

        // Try to get interface descriptions for ONU interfaces
        $ifAliases = $this->snmp->walk(self::OID_IF_ALIAS);
        foreach ($ifAliases as $ifOid => $alias) {
            if (preg_match('/\.(\d+)$/', $this->normalizeOid($ifOid), $m)) {
                $ifIndex = $m[1];
                // For BDCOM, ONU interfaces might have ifIndex values that match ONU IDs
                foreach (array_keys($indexed) as $index) {
                    if ($indexed[$index]['ONU_id'] == $ifIndex) {
                        if (empty($indexed[$index]['ONU_type']) ||
                            (strpos($alias, 'ONU') !== false &&
                             (empty($indexed[$index]['ONU_type']) || strpos($indexed[$index]['ONU_type'], 'ONU') === false))) {
                            $indexed[$index]['ONU_type'] = $alias !== '' ? trim($alias, '"') : null;
                        }
                        break;
                    }
                }
            }
        }

        // For BDCOM, the ONU_id (Gauge32 value) can be used as a unique identifier
        // Convert ONU_id to a string for serial_number
        $onus = [];
        foreach ($indexed as $index => $data) {
            // Use ONU_id as serial_number if we don't have a better identifier
            $serialNumber = $data['ONU_id'] ?? null;

            // Check if this is a valid ONU (has ONU_id or type containing ONU)
            if ($serialNumber === null && $serialNumber !== '0') {
                continue;
            }

            // Convert ONU_id to string if it's numeric
            if (is_numeric($serialNumber)) {
                $serialNumber = (string) $serialNumber;
            }

            $onus[] = [
                'serial_number' => $serialNumber,
                'mac_address' => null, // BDCOM doesn't expose MAC in the main table
                'pon_port' => null, // PON port info not in this table
                'pon_port_name' => null,
                'ONU_id' => $index,
                'is_registered' => true, // Assume registered if it appears in the table
                'registered_at' => null,
                'vendor_id' => 'BDCOM',
                'firmware_version' => null,
                'hardware_version' => null,
                'ONU_type' => $data['ONU_type'] ?? 'BDCOM ONU',
            ];
        }

        return $onus;
    }

    protected function resolveOnuIndex(string $ponPort, string $onuMacOrId): ?string
    {
        if (is_numeric($onuMacOrId)) {
            return $onuMacOrId;
        }

        $normalizedMac = strtoupper(str_replace([':', '-', ' '], '', $onuMacOrId));

        $onuMacOid = $this->oidMap['onu_mac'] ?? null;
        if (!$onuMacOid) {
            return null;
        }

        $macRows = $this->snmp->walk($onuMacOid);

        foreach ($macRows as $oid => $value) {
            $formattedMac = $this->formatMacAddress($value);
            $macWithoutSeparators = strtoupper(str_replace(':', '', $formattedMac));

            if ($macWithoutSeparators === $normalizedMac) {
                if (preg_match('/\.(\d+)$/', $this->normalizeOid($oid), $m)) {
                    return $m[1];
                }
            }
        }

        return null;
    }

    protected function formatMacAddress(string $mac): string
    {
        foreach (['= Hex-STRING: ', 'Hex-STRING: ', '=HEX-STRING: ', 'HEX-STRING: ', '= ', '='] as $prefix) {
            if (str_starts_with($mac, $prefix)) {
                $mac = substr($mac, strlen($prefix));
                break;
            }
        }
        return str_replace(' ', ':', strtoupper(trim($mac)));
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

    public function getSnmpService(): SnmpService
    {
        return $this->snmp;
    }

    public function getSfpDomData(): array
    {
        // BDCOM SFP DOM OIDs (vendor-specific)
        // These may vary by model - common ones for P3310C:
        // SFP Tx Power: .1.3.6.1.4.1.3320.9.109.1.2.1.1.4.<index>
        // SFP Rx Power: .1.3.6.1.4.1.3320.9.109.1.2.1.1.5.<index>
        // SFP Temperature: .1.3.6.1.4.1.3320.9.109.1.2.1.1.6.<index>
        // SFP Voltage: .1.3.6.1.4.1.3320.9.109.1.2.1.1.7.<index>
        // SFP Tx Bias: .1.3.6.1.4.1.3320.9.109.1.2.1.1.8.<index>

        $domData = [];

        // Try to walk SFP DOM table if available
        $sfpTxPowerOid = '.1.3.6.1.4.1.3320.9.109.1.2.1.1.4';
        $sfpRxPowerOid = '.1.3.6.1.4.1.3320.9.109.1.2.1.1.5';
        $sfpTempOid = '.1.3.6.1.4.1.3320.9.109.1.2.1.1.6';
        $sfpVoltageOid = '.1.3.6.1.4.1.3320.9.109.1.2.1.1.7';
        $sfpTxBiasOid = '.1.3.6.1.4.1.3320.9.109.1.2.1.1.8';

        $txPowerRows = $this->snmp->walk($sfpTxPowerOid);
        $rxPowerRows = $this->snmp->walk($sfpRxPowerOid);
        $tempRows = $this->snmp->walk($sfpTempOid);
        $voltageRows = $this->snmp->walk($sfpVoltageOid);
        $txBiasRows = $this->snmp->walk($sfpTxBiasOid);

        $allIndices = array_unique(array_merge(
            array_keys($txPowerRows),
            array_keys($rxPowerRows),
            array_keys($tempRows),
            array_keys($voltageRows),
            array_keys($txBiasRows)
        ));

        foreach ($allIndices as $oid) {
            $normalizedOid = $this->normalizeOid($oid);
            if (!preg_match('/\.(\d+)$/', $normalizedOid, $m)) {
                continue;
            }
            $index = (int) $m[1];

            $domData[$index] = [
                'tx_power_dbm' => $this->parseDomValue($txPowerRows[$oid] ?? null, 100),
                'rx_power_dbm' => $this->parseDomValue($rxPowerRows[$oid] ?? null, 100),
                'temperature_c' => $this->parseDomValue($tempRows[$oid] ?? null, 10),
                'voltage_v' => $this->parseDomValue($voltageRows[$oid] ?? null, 1000),
                'tx_bias_ma' => $this->parseDomValue($txBiasRows[$oid] ?? null, 100),
            ];
        }

        return $domData;
    }

    protected function parseDomValue(?string $val, int $divisor): ?float
    {
        if ($val === null || $val === '') {
            return null;
        }
        if (preg_match('/(-?\d+)/', $val, $matches)) {
            $num = (float) $matches[1];
            return round($num / $divisor, 2);
        }
        return null;
    }
}
