<?php

namespace App\Services\Network;

use Illuminate\Support\Facades\Log;

class SnmpService
{
    protected string $host;
    protected string $community;
    protected string $version;

    public function __construct(string $host, string $community, string $version = '2c')
    {
        $this->host = $host;
        $this->community = $community;
        $this->version = $version;
    }

    /**
     * Perform an snmpget command.
     */
    public function get(string $oid): ?string
    {
        $command = sprintf(
            'snmpget -v %s -c %s -Oqv %s %s 2>&1',
            escapeshellarg($this->version),
            escapeshellarg($this->community),
            escapeshellarg($this->host),
            escapeshellarg($this->formatOid($oid))
        );

        return $this->execute($command);
    }

    /**
     * Perform an snmpwalk command.
     * Returns an array of OID => value
     */
    public function walk(string $oid): array
    {
        $command = sprintf(
            'snmpwalk -v %s -c %s -Oqn %s %s 2>&1',
            escapeshellarg($this->version),
            escapeshellarg($this->community),
            escapeshellarg($this->host),
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
