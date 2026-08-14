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

            if ($this->connected) {
                // Keep the underlying client's "connected" flag false so its
                // read() loop uses the timeout-safe break path. Once the flag
                // is true, read() only stops on !done — a stalled router makes
                // it loop forever instead of honoring the socket timeout.
                $this->client->connected = false;
            }

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
     * Read a response from the router, logging when the socket timed out
     * (the router stopped responding mid-operation).
     */
    protected function read(bool $parse = false): mixed
    {
        $response = $this->client->read($parse);

        if (is_resource($this->client->socket) && ($meta = stream_get_meta_data($this->client->socket)) && ($meta['timed_out'] ?? false)) {
            Log::warning("MikroTik read timed out for device #{$this->device->id}", [
                'ip' => $this->device->ip_address,
            ]);
        }

        return $response;
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
        $response = $this->read(false);
        $parsed = $this->client->parseResponse($response);

        return $parsed[0] ?? null;
    }

    /**
     * Get interface traffic stats
     */
    public function getInterfaceTraffic(?string $interfaceName = null): array
    {
        if (!$this->connect()) {
            return [];
        }

        $this->client->write('/interface/print');
        $response = $this->read(false);
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
        $response = $this->read(false);
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
        $response = $this->read(false);
        $sessions = $this->client->parseResponse($response);

        if (empty($sessions)) {
            return false;
        }

        $sessionId = $sessions[0]['.id'];

        $this->client->write('/ppp/active/remove', false);
        $this->client->write('=.id=' . $sessionId);
        $this->read(false);

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
        $response = $this->read(false);
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

        $this->read(false);
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
        $response = $this->read(false);
        $queues = $this->client->parseResponse($response);

        if (empty($queues)) {
            return true; // Already gone
        }

        $queueId = $queues[0]['.id'];

        $this->client->write('/queue/simple/remove', false);
        $this->client->write('=.id=' . $queueId);
        $this->read(false);

        return true;
    }

    /**
     * Create or update a local PPPoE secret for a subscriber.
     */
    public function setPppSecret(string $username, string $password, ?string $profile = null, ?string $remoteAddress = null, bool $disabled = false): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $this->client->write('/ppp/secret/print', false);
        $this->client->write('?name=' . $username);
        $response = $this->read(false);
        $secrets = $this->client->parseResponse($response);

        $params = [
            '=name=' . $username,
            '=password=' . $password,
            '=service=pppoe',
            '=disabled=' . ($disabled ? 'yes' : 'no'),
        ];

        if ($profile) {
            $params[] = '=profile=' . $profile;
        }

        if ($remoteAddress) {
            $params[] = '=remote-address=' . $remoteAddress;
        }

        if (!empty($secrets)) {
            $this->client->write('/ppp/secret/set', false);
            $this->client->write('=.id=' . $secrets[0]['.id'], false);
            $this->sendSentence($params);
            $this->read(false);

            return true;
        }

        $this->client->write('/ppp/secret/add', false);
        $this->sendSentence($params);
        $this->read(false);

        return true;
    }

    /**
     * Write a sentence (a list of words) to the RouterOS API and terminate
     * it with the end-of-sentence byte so the router starts responding.
     * The meklis client only appends the terminator when a write() call uses
     * its default $param2 = true — sending every word with false and never
     * terminating makes the router wait forever.
     */
    protected function sendSentence(array $words): void
    {
        $last = array_pop($words);

        foreach ($words as $word) {
            $this->client->write($word, false);
        }

        if ($last !== null) {
            $this->client->write($last);
        }
    }

    /**
     * Enable or disable a local PPPoE secret (suspend = disabled).
     */
    public function setPppSecretEnabled(string $username, bool $enabled): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $this->client->write('/ppp/secret/print', false);
        $this->client->write('?name=' . $username);
        $response = $this->read(false);
        $secrets = $this->client->parseResponse($response);

        if (empty($secrets)) {
            return false;
        }

        $this->client->write('/ppp/secret/set', false);
        $this->client->write('=.id=' . $secrets[0]['.id'], false);
        $this->client->write('=disabled=' . ($enabled ? 'no' : 'yes'));
        $this->read(false);

        return true;
    }

    /**
     * Remove a local PPPoE secret entirely (termination).
     */
    public function removePppSecret(string $username): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $this->client->write('/ppp/secret/print', false);
        $this->client->write('?name=' . $username);
        $response = $this->read(false);
        $secrets = $this->client->parseResponse($response);

        if (empty($secrets)) {
            return true; // Already gone
        }

        $this->client->write('/ppp/secret/remove', false);
        $this->client->write('=.id=' . $secrets[0]['.id']);
        $this->read(false);

        return true;
    }

    /**
     * Ensure a PPP profile exists for the given bandwidth and return its name.
     */
    public function ensurePppProfile(int $downloadMbps, int $uploadMbps): ?string
    {
        if (!$this->connect()) {
            return null;
        }

        $name = 'fiberloop-' . $downloadMbps . 'M-' . $uploadMbps . 'M';

        $this->client->write('/ppp/profile/print', false);
        $this->client->write('?name=' . $name);
        $response = $this->read(false);
        $profiles = $this->client->parseResponse($response);

        if (!empty($profiles)) {
            return $name;
        }

        // rx-rate = upload, tx-rate = download (same rx/tx ordering as Mikrotik-Rate-Limit)
        $this->client->write('/ppp/profile/add', false);
        $this->client->write('=name=' . $name, false);
        $this->client->write('=rate-limit=' . $uploadMbps . 'M/' . $downloadMbps . 'M');
        $this->read(false);

        return $name;
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
        $this->read(false);

        return true;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
