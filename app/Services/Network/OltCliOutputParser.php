<?php

namespace App\Services\Network;

/**
 * Parses vendor OLT CLI output (ONU tables and optical-power tables) into a
 * vendor-agnostic structure. The same parser is used for every vendor because
 * GPON/EPON CLI tables share a common shape; only the commands differ
 * (see config/olt.php).
 */
final class OltCliOutputParser
{
    private const STATE_WORDS = [
        'online', 'offline', 'registered', 'unregistered', 'active', 'unknown',
        'working', 'configuring', 'autofind', 'auto-find', 'auto_find', 'los',
        'losi', 'fail', 'deactive', 'inactive', 'normal', 'pending',
    ];

    /**
     * Parse an "ONU info/list" table into OnuInfo rows.
     */
    public static function parseOnuTable(string $output, ?string $defaultPort = null): array
    {
        $onus = [];

        foreach (self::dataLines($output) as $line) {
            $row = self::parseOnuRow(preg_split('/\s+/', $line), $defaultPort);

            if ($row === null) {
                continue;
            }

            $onus[] = new OnuInfo(
                serialNumber: $row['serial_number'],
                macAddress: $row['mac_address'],
                ponPort: $row['pon_port'],
                ponPortName: $row['pon_port_name'],
                onuId: $row['ONU_id'],
                isRegistered: $row['is_registered'],
                isOnline: $row['is_online'],
            );
        }

        return $onus;
    }

    /**
     * Parse an optical-power table into rows keyed by "{port}|{onu_id}".
     */
    public static function parseOpticalTable(string $output, ?string $defaultPort = null): array
    {
        $rows = [];
        $columns = null;

        foreach (preg_split('/\r\n|\r|\n/', $output) as $raw) {
            $line = trim(preg_replace('/[ \t]+/', ' ', $raw));

            if ($line === '' || self::isTableDecor($line)) {
                continue;
            }

            $tokens = preg_split('/\s+/', $line);

            // A header row only maps the ORDER of the numeric columns, so a
            // multi-word header like "ONU Name" does not shift the indexes.
            $header = self::matchOpticalHeader($tokens);
            if ($header !== null) {
                $columns = $header;

                continue;
            }

            $row = self::parseOpticalRow($tokens, $columns, $defaultPort);

            if ($row === null) {
                continue;
            }

            $rows[$row['key']] = $row;
        }

        return $rows;
    }

    /**
     * Normalize a MAC to "AA:BB:CC:DD:EE:FF".
     */
    public static function normalizeMac(string $mac): string
    {
        $mac = preg_replace('/[^0-9A-Fa-f]/', '', $mac);

        if (strlen((string) $mac) !== 12) {
            return strtoupper((string) $mac);
        }

        return strtoupper(implode(':', str_split($mac, 2)));
    }

    /**
     * Parse a VSOL "show onu basic-info" table into rows keyed by "{port}|{onu_id}".
     *
     * Column order is fixed for this vendor:
     *   ONU-ID  VendorID  Model  ID  hwVer  SwVer  Type  Interface Type
     */
    public static function parseBasicInfoTable(string $output): array
    {
        $rows = [];

        foreach (self::dataLines($output) as $line) {
            $tokens = preg_split('/\s+/', $line);

            if ($tokens === false || $tokens === []) {
                continue;
            }

            [$port, $onuId] = self::extractPortAndOnu($tokens[0]);

            if ($port === null || $onuId === null) {
                continue;
            }

            $key = $port . '|' . $onuId;

            $rows[$key] = [
                'key' => $key,
                'port' => (string) $port,
                'onu_id' => (string) $onuId,
                'vendor_id' => $tokens[1] ?? null,
                'model' => $tokens[2] ?? null,
                'mac_address' => self::normalizeMac($tokens[3] ?? ''),
                'hardware_version' => $tokens[4] ?? null,
                'firmware_version' => $tokens[5] ?? null,
                'ONU_type' => $tokens[6] ?? null,
                'interface_type' => implode(' ', array_slice($tokens, 7)),
            ];
        }

        return $rows;
    }

    /**
     * Parse VSOL ONU descriptions out of `show running-config`.
     *
     * The running config groups ONUs per PON port:
     *   interface epon 0/1
     *     onu 1 description Joshim_shop
     *     ...
     *   interface epon 0/2
     *     onu 1 description Isa_Dhalipara
     *
     * Returns rows keyed by "{port}|{onu_id}".
     */
    public static function parseDescriptionsTable(string $output): array
    {
        $rows = [];
        $currentPort = null;

        foreach (preg_split('/\r\n|\r|\n/', $output) as $raw) {
            $line = trim($raw);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^interface\s+epon\s+(\d+)\/(\d+)$/i', $line, $m)) {
                $currentPort = (int) $m[2];

                continue;
            }

            if ($currentPort === null) {
                continue;
            }

            if (preg_match('/^onu\s+(\d+)\s+description\s+(.+)$/i', $line, $m)) {
                $onuId = (int) $m[1];
                $description = trim($m[2], " \t\"'");

                if ($description === '') {
                    continue;
                }

                $rows[$currentPort . '|' . $onuId] = $description;
            }
        }

        return $rows;
    }

    /**
     * Parse a VSOL "show pon info" block for one PON port.
     */
    public static function parsePonInfo(string $output): array
    {
        $admin = null;
        $oper = null;

        foreach (preg_split('/\r\n|\r|\n/', $output) as $raw) {
            $line = trim($raw);

            if (preg_match('/^PON\s+Admin\s+Status\s*[:=]\s*(\w+)/i', $line, $m)) {
                $admin = strtolower($m[1]) === 'enable' ? 1 : 2;
            } elseif (preg_match('/^PON\s+Link\s+status\s*[:=]\s*(\w+)/i', $line, $m)) {
                $oper = strtolower($m[1]) === 'enable' ? 1 : 2;
            }
        }

        return [
            'admin_status' => $admin,
            'oper_status' => $oper,
        ];
    }

    /**
     * Extract the trailing integer from an F/S/P identifier ("0/1/7" -> 7).
     */
    public static function portInt(string $fsp): int
    {
        $parts = explode('/', $fsp);

        return (int) end($parts);
    }

    private static function dataLines(string $output): array
    {
        $lines = [];

        foreach (preg_split('/\r\n|\r|\n/', $output) as $raw) {
            $line = trim(preg_replace('/[ \t]+/', ' ', $raw));

            if ($line === '' || self::isTableDecor($line) || self::isHeaderLine($line)) {
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private static function isTableDecor(string $line): bool
    {
        return (bool) preg_match('/^[-+=#*_.\s]+$/', $line);
    }

    private static function isHeaderLine(string $line): bool
    {
        // Data rows virtually always carry an F/S/P, a MAC, a serial number, or
        // an index/ID — all of which contain digits. Header rows are pure words
        // ("Description", "Voltage", ... may be longer than the serial regex).
        if (preg_match('/\d/', $line)) {
            return false;
        }

        if (count(preg_split('/\s+/', $line)) < 2) {
            return false;
        }

        return (bool) preg_match(
            '/\b(index|f\/s\/p|port|pon|onu|ont|sn\b|serial|state|status|type|time|mac|description|name|rx|tx|temp|voltage|bias)\b/i',
            $line
        );
    }

    private static function parseOnuRow(array $tokens, ?string $defaultPort): ?array
    {
        $pon = null;
        $ponName = $defaultPort;
        $onuId = null;
        $sn = null;
        $mac = null;
        $state = null;

        foreach ($tokens as $tok) {
            $clean = trim($tok, '":;, ');

            if ($clean === '') {
                continue;
            }

            $lower = strtolower($clean);
            if (in_array($lower, self::STATE_WORDS, true)) {
                $state = $lower;

                continue;
            }

            [$port, $embeddedOnu] = self::extractPortAndOnu($clean);
            if ($port !== null) {
                $pon = $port;
                $ponName = self::findPortName($clean) ?? $ponName;
                if ($embeddedOnu !== null) {
                    $onuId = $embeddedOnu;
                }

                continue;
            }

            if ($onuId === null && $pon !== null && preg_match('/^\d{1,3}$/', $clean)) {
                $onuId = (int) $clean;

                continue;
            }

            if ($mac === null && preg_match('/^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/', $clean)) {
                $mac = self::normalizeMac($clean);

                continue;
            }

            if ($sn === null && preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{5,19}$/', $clean)) {
                $sn = strtoupper($clean);
            }
        }

        if ($pon === null && $onuId === null && $sn === null && $mac === null) {
            return null;
        }

        // A pure-word token like a dereg reason ("Timeout", "Power Off") can
        // pass the serial regex. When the row carries a MAC (EPON), an
        // all-letter serial candidate is treated as noise — the MAC is the
        // device identity.
        if ($sn !== null && $mac !== null && !preg_match('/\d/', $sn)) {
            $sn = null;
        }

        return [
            'pon_port' => $pon,
            'pon_port_name' => $ponName,
            'ONU_id' => $onuId !== null ? (string) $onuId : null,
            'serial_number' => $sn ?? $mac,
            'mac_address' => $mac,
            'is_registered' => self::isRegisteredState($state),
            'is_online' => self::isOnlineState($state),
        ];
    }

    private static function matchOpticalHeader(array $tokens): ?array
    {
        $fields = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $t = strtolower(trim($tokens[$i], '():; '));
            $next = $i + 1 < $count ? strtolower(trim($tokens[$i + 1], '():; ')) : '';

            $field = null;

            if (preg_match('/temp(erature)?/', $t)) {
                $field = 'temp';
            } elseif (preg_match('/voltage|volt/', $t)) {
                $field = 'volt';
            } elseif (preg_match('/^[rt][xy]$/', $t) && preg_match('/bias|current/', $next)) {
                // "TX Bias Current(mA)" — the RX/TX marker modifies the bias column,
                // so it must not register an rx/tx field of its own.
                $field = null;
            } elseif (preg_match('/^[rt][xy]/', $t) && preg_match('/^power/', $next)) {
                // "Rx Power(dBm)" / "RX Power(dBm)" — marker and unit are one column.
                $field = $t[0] === 'r' ? 'rx' : 'tx';
            } elseif (preg_match('/current|bias/', $t)) {
                $field = 'bias';
            } elseif (preg_match('/rx[\s_\-]*(power)?|receive|rssi/', $t)) {
                $field = 'rx';
            } elseif (preg_match('/tx[\s_\-]*(power)?|transmit/', $t)) {
                $field = 'tx';
            }

            if ($field === null) {
                continue;
            }

            // Multi-word columns ("Bias Current(mA)") must not register twice.
            $last = $fields[array_key_last($fields) ?? -1] ?? null;
            if ($last !== $field) {
                $fields[] = $field;
            }
        }

        if (empty($fields)) {
            return null;
        }

        // A real data row always carries an F/S/P or a numeric value.
        foreach ($tokens as $tok) {
            if (preg_match('#\d+/\d+/\d+#', $tok)) {
                return null;
            }

            if (self::parseFloat($tok) !== null) {
                return null;
            }
        }

        return $fields;
    }

    private static function parseOpticalRow(array $tokens, ?array $columns, ?string $defaultPort): ?array
    {
        $port = null;
        $onuId = null;
        $numbers = [];

        foreach ($tokens as $tok) {
            $clean = trim($tok, '":; ');

            if ($clean === '') {
                continue;
            }

            [$embeddedPort, $embeddedOnu] = self::extractPortAndOnu($clean);
            if ($embeddedPort !== null) {
                $port = $embeddedPort;
                if ($embeddedOnu !== null) {
                    $onuId = $embeddedOnu;
                }

                continue;
            }

            if (str_contains($clean, '.')) {
                $float = self::parseFloat($clean);
                if ($float !== null) {
                    $numbers[] = $float;
                }

                continue;
            }

            if ($onuId === null && preg_match('/^\d{1,4}$/', $clean)) {
                $onuId = (int) $clean;
            }
        }

        if ($port === null && $onuId === null) {
            return null;
        }

        $rx = $tx = $temp = $volt = $bias = null;
        $fields = $columns ?? ['rx', 'tx', 'temp', 'volt', 'bias'];

        foreach ($fields as $i => $field) {
            if (!isset($numbers[$i])) {
                continue;
            }

            match ($field) {
                'rx' => $rx = $numbers[$i],
                'tx' => $tx = $numbers[$i],
                'temp' => $temp = $numbers[$i],
                'volt' => $volt = $numbers[$i],
                'bias' => $bias = $numbers[$i],
                default => null,
            };
        }

        $portString = (string) ($port ?? self::portInt((string) $defaultPort));
        $onuString = $onuId !== null ? (string) $onuId : '?';

        return [
            'key' => $portString . '|' . $onuString,
            'port' => $portString,
            'onu_id' => $onuString,
            'rx_power_dbm' => $rx,
            'tx_power_dbm' => $tx,
            'temperature_c' => $temp,
            'voltage_v' => $volt,
            'tx_bias_ma' => $bias,
            'is_online' => $rx !== null,
        ];
    }

    /**
     * Extract port and optionally embedded ONU id from a token.
     * Handles "0/1/7", "EPON0/1:20" and "gpon-onu_1/1/1:3" style tokens.
     */
    private static function extractPortAndOnu(string $token): array
    {
        // Dates like "2026/08/14" must not be read as an F/S/P port.
        if (preg_match('#\d{4}/\d{1,2}/\d{1,2}#', $token)) {
            return [null, null];
        }

        if (preg_match('#epon\s*(\d+)/(\d+):(\d+)#i', $token, $m)) {
            return [self::portInt($m[1] . '/' . $m[2]), (int) $m[3]];
        }

        if (preg_match('#gpon[-_]?onu[_-]*(\d+/\d+/\d+):(\d+)#i', $token, $m)) {
            return [self::portInt($m[1]), (int) $m[2]];
        }

        if (preg_match('#(\d+/\d+/\d+)#', $token, $m)) {
            return [self::portInt($m[1]), null];
        }

        return [null, null];
    }

    private static function findPortName(string $token): ?string
    {
        if (preg_match('#epon\s*(\d+)/(\d+):(\d+)#i', $token, $m)) {
            return $m[1] . '/' . $m[2];
        }

        if (preg_match('#(\d+/\d+/\d+)#', $token, $m)) {
            return $m[1];
        }

        return null;
    }

    private static function parseFloat(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = trim($value, '()" ;:');

        if (preg_match('/-?\d+(\.\d+)?/', $clean, $m)) {
            return (float) $m[0];
        }

        return null;
    }

    private static function isRegisteredState(?string $state): bool
    {
        if ($state === null) {
            return true;
        }

        return !in_array($state, [
            'unregistered', 'autofind', 'auto-find', 'auto_find',
            'configuring', 'pending', 'fail', 'los', 'losi', 'unknown',
        ], true);
    }

    private static function isOnlineState(?string $state): bool
    {
        if ($state === null) {
            return true;
        }

        return in_array($state, ['online', 'active', 'working', 'normal', 'registered'], true);
    }
}
