<?php

namespace App\Services\Network;

use Illuminate\Support\Facades\Log;

class SnmpService
{
    protected string $host;
    protected string $community;
    protected string $version;
    protected int $port;

    public function __construct(string $host, string $community, string $version = '2c', int $port = 161)
    {
        $this->host = $host;
        $this->community = $community;
        $this->version = $version;
        $this->port = $port;
    }

    /**
     * Normalize SNMP version for command line tools.
     * Converts 'v1' to '1', 'v2c' to '2c', 'v3' to '3'
     */
    protected function normalizeVersion(): string
    {
        return match ($this->version) {
            'v1' => '1',
            'v2c' => '2c',
            'v3' => '3',
            default => $this->version,
        };
    }

    /**
     * Get the host with port for SNMP commands.
     * Newer net-snmp versions use HOST:PORT instead of -p flag.
     */
    protected function getHostWithPort(): string
    {
        if ($this->port === 161) {
            return $this->host;
        }
        return $this->host . ':' . $this->port;
    }

    /**
     * Perform an snmpget command.
     */
    public function get(string $oid): ?string
    {
        $command = sprintf(
            'snmpget -v %s -c %s %s %s 2>&1',
            escapeshellarg($this->normalizeVersion()),
            escapeshellarg($this->community),
            escapeshellarg($this->getHostWithPort()),
            escapeshellarg($this->formatOid($oid))
        );

        $raw = $this->execute($command);
        if ($raw === null) {
            return null;
        }

        // Parse the output to extract the value
        // Format: OID = TYPE: value
        if (preg_match('/= [A-Z]+: (.+)$/m', $raw, $matches)) {
            return trim($matches[1]);
        }

        // If the above doesn't match, try simpler formats
        if (preg_match('/= (.+)$/m', $raw, $matches)) {
            return trim($matches[1]);
        }

        return $raw;
    }

    /**
     * Perform an snmpwalk command.
     * Returns an array of OID => value
     */
    public function walk(string $oid): array
    {
        $command = sprintf(
            'snmpwalk -v %s -c %s %s %s -Oqn 2>&1',
            escapeshellarg($this->normalizeVersion()),
            escapeshellarg($this->community),
            escapeshellarg($this->getHostWithPort()),
            escapeshellarg($this->formatOid($oid))
        );

        $output = $this->executeList($command);
        $result = [];

        foreach ($output as $line) {
            if (strpos($line, 'No Such Object') !== false || strpos($line, 'Timeout') !== false) {
                continue;
            }

            $parts = explode(' ', $line, 2);
            if (count($parts) === 2) {
                $oidPath = trim($parts[0]);
                $value = trim($parts[1]);

                // Remove type prefixes like "= INTEGER: 1", "Hex-STRING: 00 11 22", "INTEGER: 1", etc.
                // First remove leading "=" if present
                if (str_starts_with($value, '=')) {
                    $value = ltrim(substr($value, 1));
                }
                // Then remove type prefix like "INTEGER: ", "Hex-STRING: ", etc. (case-insensitive)
                if (preg_match('/^[A-Z][A-Za-z0-9-]*:\s*/', $value, $matches)) {
                    $prefix = $matches[0];
                    $value = substr($value, strlen($prefix));
                }

                // Remove quotes
                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                }
                $result[$oidPath] = $value;
            }
        }

        return $result;
    }

    protected function formatOid(string $oid): string
    {
        // Add leading dot if missing, as required by some tools
        if (strpos($oid, '.') !== 0 && is_numeric($oid[0])) {
            return '.' . $oid;
        }
        return $oid;
    }

    protected function execute(string $command): ?string
    {
        exec($command, $output, $resultCode);

        if ($resultCode !== 0) {
            Log::warning("SNMP command failed", [
                'command' => $command,
                'output' => $output,
                'code' => $resultCode
            ]);
            return null;
        }

        if (empty($output)) {
            return null;
        }

        $result = implode("\n", $output);

        // Remove quotes
        if (preg_match('/^"(.*)"$/s', $result, $matches)) {
            $result = $matches[1];
        }

        // Remove type prefixes like "INTEGER: 1"
        if (preg_match('/^[A-Z0-9]+:\s*(.*)$/s', $result, $matches)) {
            $result = $matches[1];
        }

        return trim($result);
    }

    protected function executeList(string $command): array
    {
        exec($command, $output, $resultCode);

        if ($resultCode !== 0) {
            Log::warning("SNMP walk command failed", [
                'command' => $command,
                'output' => $output,
                'code' => $resultCode
            ]);
            return [];
        }

        return $output;
    }
}
