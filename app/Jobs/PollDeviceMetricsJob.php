<?php

namespace App\Jobs;

use App\Models\DeviceMetric;
use App\Models\Incident;
use App\Models\NetworkDevice;
use App\Services\Network\MikroTikService;
use App\Services\Network\SnmpService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollDeviceMetricsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(public readonly NetworkDevice $device)
    {
    }

    public function handle(): void
    {
        $pingMs = $this->ping($this->device->ip_address);
        $status = $this->resolveStatus($pingMs);

        $cpuUsage = null;
        $memUsage = null;
        $uptimeSeconds = null;
        $interfaceStats = null;
        $additionalData = null;

        // Try MikroTik RouterOS API for richer metrics if vendor is mikrotik
        if ($this->device->vendor?->value === 'mikrotik') {
            try {
                $mikrotik = new MikroTikService($this->device);
                $resource = $mikrotik->getSystemResource();
                if ($resource) {
                    $cpuUsage = isset($resource['cpu-load']) ? (float) $resource['cpu-load'] : null;
                    $memUsage = $this->calcMemUsagePercent($resource);
                    $uptimeSeconds = $this->parseUptime($resource['uptime'] ?? null);
                    $additionalData = [
                        'version' => $resource['version'] ?? null,
                        'board-name' => $resource['board-name'] ?? null,
                        'free-hdd-space' => $resource['free-hdd-space'] ?? null,
                    ];
                }
                $interfaces = $mikrotik->getInterfaceTraffic();
                if ($interfaces) {
                    $interfaceStats = array_map(fn ($i) => [
                        'name' => $i['name'] ?? null,
                        'type' => $i['type'] ?? null,
                        'tx-byte' => $i['tx-byte'] ?? null,
                        'rx-byte' => $i['rx-byte'] ?? null,
                        'running' => $i['running'] ?? null,
                    ], array_slice($interfaces, 0, 20)); // cap at 20 ifaces
                }
            } catch (Exception $e) {
                Log::warning("MikroTik API poll failed for device {$this->device->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // SNMP fallback for uptime if RouterOS API didn't give it
        if ($uptimeSeconds === null && $this->device->snmp_community) {
            try {
                $snmp = new SnmpService(
                    $this->device->ip_address,
                    $this->device->snmp_community,
                    $this->device->snmp_version ?? '2c'
                );
                // sysUpTime OID — returns centiseconds
                $sysUptime = $snmp->get('1.3.6.1.2.1.1.3.0');
                if ($sysUptime !== null) {
                    // sysUpTime is in hundredths of a second
                    $uptimeSeconds = (int) ($sysUptime / 100);
                }
            } catch (Exception $e) {
                Log::warning("SNMP poll failed for device {$this->device->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        DeviceMetric::create([
            'network_device_id' => $this->device->id,
            'status' => $status,
            'uptime_seconds' => $uptimeSeconds,
            'cpu_usage_percent' => $cpuUsage,
            'memory_usage_percent' => $memUsage,
            'ping_response_ms' => $pingMs,
            'interface_stats' => $interfaceStats,
            'additional_data' => $additionalData,
        ]);

        // Update the live reachability flag on the device
        $wasReachable = $this->device->is_reachable;
        $isReachable = $status !== 'down';
        
        $this->device->update([
            'is_reachable' => $isReachable,
            'last_checked_at' => now(),
        ]);

        if ($wasReachable && !$isReachable) {
            $this->createOutageIncident();
        } elseif (!$wasReachable && $isReachable) {
            $this->resolveOutageIncident();
        }
    }

    protected function createOutageIncident(): void
    {
        $existing = Incident::where('network_device_id', $this->device->id)
            ->where('status', 'open')
            ->where('title', 'like', 'Device Down:%')
            ->first();

        if (!$existing) {
            Incident::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'title' => "Device Down: {$this->device->name}",
                'description' => "Device at {$this->device->ip_address} is unreachable.",
                'status' => 'open',
                'severity' => 'critical',
                'network_device_id' => $this->device->id,
                'started_at' => now(),
            ]);
        }
    }

    protected function resolveOutageIncident(): void
    {
        Incident::where('network_device_id', $this->device->id)
            ->where('status', 'open')
            ->where('title', 'like', 'Device Down:%')
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
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

    private function calcMemUsagePercent(array $resource): ?float
    {
        $total = (int) ($resource['total-memory'] ?? 0);
        $free = (int) ($resource['free-memory'] ?? 0);
        if ($total === 0) {
            return null;
        }

        return round(($total - $free) / $total * 100, 2);
    }

    /**
     * Parse MikroTik uptime string like "5d4h3m2s" to total seconds.
     */
    private function parseUptime(?string $uptime): ?int
    {
        if (!$uptime) {
            return null;
        }

        $seconds = 0;
        if (preg_match('/(\d+)w/', $uptime, $m)) {
            $seconds += (int) $m[1] * 604800;
        }
        if (preg_match('/(\d+)d/', $uptime, $m)) {
            $seconds += (int) $m[1] * 86400;
        }
        if (preg_match('/(\d+)h/', $uptime, $m)) {
            $seconds += (int) $m[1] * 3600;
        }
        if (preg_match('/(\d+)m/', $uptime, $m)) {
            $seconds += (int) $m[1] * 60;
        }
        if (preg_match('/(\d+)s/', $uptime, $m)) {
            $seconds += (int) $m[1];
        }

        return $seconds > 0 ? $seconds : null;
    }
}
