<?php

namespace App\Services\Network;

use App\Models\Incident;
use App\Models\NetworkDevice;

class DeviceReachabilityService
{
    /**
     * Ping the device and update its live reachability flag.
     *
     * Returns an array describing the result:
     * [
     *   'reachable' => bool,
     *   'status'    => 'up' | 'degraded' | 'down',
     *   'ping_ms'   => int|null,
     * ]
     */
    public function check(NetworkDevice $device): array
    {
        $pingMs = $this->ping($device->ip_address);
        $status = $this->resolveStatus($pingMs);
        $reachable = $status !== 'down';

        $wasReachable = $device->is_reachable;

        $device->update([
            'is_reachable' => $reachable,
            'last_checked_at' => now(),
        ]);

        if ($wasReachable && !$reachable) {
            $this->createOutageIncident($device);
        } elseif (!$wasReachable && $reachable) {
            $this->resolveOutageIncident($device);
        }

        return [
            'reachable' => $reachable,
            'status' => $status,
            'ping_ms' => $pingMs,
        ];
    }

    /**
     * Ping the device — returns round-trip ms, or null if unreachable.
     */
    private function ping(string $ip): ?int
    {
        $escaped = escapeshellarg($ip);
        $output = [];
        exec("ping -c 1 -W 3 {$escaped} 2>/dev/null", $output, $code);

        if ($code !== 0) {
            return null;
        }

        // Parse "time=X.X ms" from ping output
        foreach ($output as $line) {
            if (preg_match('/time[=<]([\d.]+)\s*ms/', $line, $m)) {
                return (int) round((float) $m[1]);
            }
        }

        return null;
    }

    private function resolveStatus(?int $pingMs): string
    {
        if ($pingMs === null) {
            return 'down';
        }
        // >200 ms on a LAN link = degraded
        return $pingMs > 200 ? 'degraded' : 'up';
    }

    protected function createOutageIncident(NetworkDevice $device): void
    {
        $existing = Incident::where('network_device_id', $device->id)
            ->where('status', 'open')
            ->where('title', 'like', 'Device Down:%')
            ->first();

        if (!$existing) {
            Incident::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'title' => "Device Down: {$device->name}",
                'description' => "Device at {$device->ip_address} is unreachable.",
                'status' => 'open',
                'severity' => 'critical',
                'network_device_id' => $device->id,
                'started_at' => now(),
            ]);
        }
    }

    protected function resolveOutageIncident(NetworkDevice $device): void
    {
        Incident::where('network_device_id', $device->id)
            ->where('status', 'open')
            ->where('title', 'like', 'Device Down:%')
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }
}
