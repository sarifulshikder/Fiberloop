<?php

namespace App\Services\Network;

use App\Models\NetworkDevice;
use Exception;
use Illuminate\Support\Facades\Log;
use RouterosAPI;

class MikroTikService
{
    protected RouterosAPI $client;
    protected NetworkDevice $device;
    protected bool $connected = false;

    public function __construct(NetworkDevice $device, ?RouterosAPI $client = null)
    {
        $this->device = $device;
        $this->client = $client ?? new RouterosAPI();

        // Settings
        $this->client->debug = config('app.debug') ? false : false;

        // Timeout
        $this->client->timeout = 5;
    }

    public function connect(): bool
    {
        if ($this->connected) {
            return true;
        }

        try {
            // Decrypt password if it's stored encrypted, else use raw
            $password = $this->device->password;

            $this->connected = $this->client->connect(
                $this->device->ip_address,
                $this->device->username,
                $password,
                $this->device->api_port ?? 8728
            );

            return $this->connected;
        } catch (Exception $e) {
            Log::error("Failed to connect to MikroTik Device {$this->device->id}", [
                'error' => $e->getMessage(),
                'ip' => $this->device->ip_address
            ]);
            return false;
        }
    }

    public function disconnect(): void
    {
        if ($this->connected) {
            $this->client->disconnect();
            $this->connected = false;
        }
    }

    /**
     * Get system resource info (CPU, Memory, Uptime, Version)
     */
    public function getSystemResource(): ?array
    {
        if (!$this->connect()) {
            return null;
        }

        $this->client->write('/system/resource/print');
        $response = $this->client->read(false);
        $parsed = $this->client->parseResponse($response);

        return $parsed[0] ?? null;
    }

    /**
     * Get interface traffic stats
     */
    public function getInterfaceTraffic(string $interfaceName = null): array
    {
        if (!$this->connect()) {
            return [];
        }

        $this->client->write('/interface/print');
        $response = $this->client->read(false);
        $interfaces = $this->client->parseResponse($response);

        if ($interfaceName) {
            return array_filter($interfaces, fn ($iface) => ($iface['name'] ?? '') === $interfaceName);
        }

        return $interfaces;
    }

    /**
     * Get active PPPoE sessions
     */
    public function getActivePppoeSessions(): array
    {
        if (!$this->connect()) {
            return [];
        }

        $this->client->write('/ppp/active/print');
        $response = $this->client->read(false);
        return $this->client->parseResponse($response);
    }

    /**
     * Disconnect a PPPoE session by username
     */
    public function disconnectPppoeSession(string $username): bool
    {
        if (!$this->connect()) {
            return false;
        }

        // Find the session ID
        $this->client->write('/ppp/active/print', false);
        $this->client->write('?name=' . $username);
        $response = $this->client->read(false);
        $sessions = $this->client->parseResponse($response);

        if (empty($sessions)) {
            return false;
        }

        $sessionId = $sessions[0]['.id'];

        $this->client->write('/ppp/active/remove', false);
        $this->client->write('=.id=' . $sessionId);
        $this->client->read(false);

        return true;
    }

    /**
     * Apply or adjust a simple queue
     */
    public function setSimpleQueue(string $name, string $target, string $maxLimit): bool
    {
        if (!$this->connect()) {
            return false;
        }

        // Check if queue exists
        $this->client->write('/queue/simple/print', false);
        $this->client->write('?name=' . $name);
        $response = $this->client->read(false);
        $queues = $this->client->parseResponse($response);

        if (!empty($queues)) {
            // Update
            $queueId = $queues[0]['.id'];
            $this->client->write('/queue/simple/set', false);
            $this->client->write('=.id=' . $queueId, false);
            $this->client->write('=max-limit=' . $maxLimit, false);
            $this->client->write('=target=' . $target);
        } else {
            // Add
            $this->client->write('/queue/simple/add', false);
            $this->client->write('=name=' . $name, false);
            $this->client->write('=target=' . $target, false);
            $this->client->write('=max-limit=' . $maxLimit);
        }

        $this->client->read(false);
        return true;
    }

    /**
     * Remove a simple queue
     */
    public function removeSimpleQueue(string $name): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $this->client->write('/queue/simple/print', false);
        $this->client->write('?name=' . $name);
        $response = $this->client->read(false);
        $queues = $this->client->parseResponse($response);

        if (empty($queues)) {
            return true; // Already gone
        }

        $queueId = $queues[0]['.id'];

        $this->client->write('/queue/simple/remove', false);
        $this->client->write('=.id=' . $queueId);
        $this->client->read(false);

        return true;
    }

    /**
     * Reboot the router
     */
    public function reboot(): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $this->client->write('/system/reboot');
        $this->client->read(false);

        return true;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
