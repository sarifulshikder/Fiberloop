<?php

namespace App\Services\Network;

use App\Models\NetworkDevice;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;
use RuntimeException;
use Throwable;

/**
 * SSH CLI transport for OLTs. Wraps phpseclib3 with the device's saved
 * credentials and returns raw command output for the vendor drivers to parse.
 */
class CliTransport
{
    protected ?SSH2 $connection = null;

    public function __construct(protected NetworkDevice $device)
    {
    }

    /**
     * Open an SSH session. Returns false when the device is unreachable or the
     * credentials are rejected.
     */
    public function connect(): bool
    {
        if ($this->connection !== null) {
            return true;
        }

        try {
            $host = $this->device->ip_address;
            $port = (int) ($this->device->port ?: 22);
            $username = $this->device->username;
            $password = $this->device->password;
            $timeout = (int) config('olt.ssh_timeout', 10);

            if ($username === null || $username === '') {
                throw new RuntimeException("SSH username not configured for device {$this->device->id}");
            }

            $this->connection = new SSH2($host, $port, $timeout);
            $this->connection->setTimeout($timeout);

            if (!$this->connection->login($username, (string) $password)) {
                throw new RuntimeException("SSH login failed for {$host}:{$port}");
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('CliTransport: connect failed', [
                'device_id' => $this->device->id,
                'error' => $e->getMessage(),
            ]);

            $this->connection = null;

            return false;
        }
    }

    /**
     * Run a single command and return its raw output. Throws when the SSH
     * session could not be established.
     */
    public function exec(string $command, ?int $timeout = null): string
    {
        if (!$this->connect()) {
            throw new RuntimeException("Unable to establish SSH connection to device {$this->device->id}");
        }

        if ($timeout !== null) {
            $this->connection->setTimeout($timeout);
        }

        $output = $this->connection->exec($command);

        return trim((string) $output);
    }

    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    public function disconnect(): void
    {
        if ($this->connection === null) {
            return;
        }

        try {
            $this->connection->disconnect();
        } catch (Throwable) {
            // Best-effort teardown.
        }

        $this->connection = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
