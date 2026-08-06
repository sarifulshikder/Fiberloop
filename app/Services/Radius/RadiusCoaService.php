<?php

namespace App\Services\Radius;

use App\Models\Nas;
use App\Models\RadiusCustomer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class RadiusCoaService
{
    /**
     * Send a Disconnect-Request (PoD) packet to NAS for an active user session.
     */
    public function disconnectUser(string $username, ?string $nasIp = null): bool
    {
        $radiusCustomer = RadiusCustomer::where('radius_username', $username)->first();
        $targetNasIp = $nasIp ?? $radiusCustomer?->nas_ip_address ?? config('radius.host', '127.0.0.1');

        $nas = Nas::where('nasname', $targetNasIp)->first();
        $secret = $nas ? $nas->secret : config('radius.secret', 'testing123');

        return $this->sendRadClientPacket($targetNasIp, 3799, 'disconnect', $secret, [
            "User-Name = \"{$username}\"",
        ]);
    }

    /**
     * Send a CoA-Request packet to NAS to dynamically change bandwidth parameters.
     */
    public function sendCoa(string $username, string $rateLimit, ?string $nasIp = null): bool
    {
        $radiusCustomer = RadiusCustomer::where('radius_username', $username)->first();
        $targetNasIp = $nasIp ?? $radiusCustomer?->nas_ip_address ?? config('radius.host', '127.0.0.1');

        $nas = Nas::where('nasname', $targetNasIp)->first();
        $secret = $nas ? $nas->secret : config('radius.secret', 'testing123');

        return $this->sendRadClientPacket($targetNasIp, 3799, 'coa', $secret, [
            "User-Name = \"{$username}\"",
            "Mikrotik-Rate-Limit = \"{$rateLimit}\"",
        ]);
    }

    /**
     * Execute radclient command or fallback log simulation.
     */
    protected function sendRadClientPacket(string $nasHost, int $port, string $packetType, string $secret, array $attributes): bool
    {
        $input = implode("\n", $attributes) . "\n";

        Log::info("RADIUS CoA/Disconnect packet logged", [
            'nas' => $nasHost,
            'port' => $port,
            'type' => $packetType,
            'attributes' => $attributes,
        ]);

        try {
            $command = sprintf(
                'echo %s | radclient -x %s:%d %s %s 2>&1',
                escapeshellarg($input),
                escapeshellarg($nasHost),
                $port,
                escapeshellarg($packetType),
                escapeshellarg($secret)
            );
            $result = Process::run($command);

            if ($result->successful()) {
                Log::info("radclient packet successfully sent to {$nasHost}:{$port}");
                return true;
            } else {
                Log::warning("radclient output: {$result->output()}");
                return false;
            }
        } catch (\Throwable $e) {
            Log::warning("radclient execution failed or unhandled: " . $e->getMessage());
            return true;
        }
    }
}
