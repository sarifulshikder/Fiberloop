<?php

namespace App\Jobs;

use App\Models\Incident;
use App\Models\Onu;
use App\Services\Network\OltDrivers\OltDriverFactory;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollOnuOpticalSignalJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    // Alert threshold in dBm (e.g., -27 dBm is typically a critical low signal for GPON/EPON)
    public const CRITICAL_RX_POWER_THRESHOLD = -27.0;

    public function __construct(public readonly Onu $onu)
    {
    }

    public function handle(): void
    {
        if (!$this->onu->olt || !$this->onu->olt->networkDevice || !$this->onu->olt->networkDevice->is_active) {
            return;
        }

        try {
            $driver = OltDriverFactory::make($this->onu->olt);

            $rxPower = $driver->getOnuRxPower((string) $this->onu->pon_port, (string) $this->onu->ONU_id);
            $txPower = $driver->getOnuTxPower((string) $this->onu->pon_port, (string) $this->onu->ONU_id);
            $isOnline = $driver->isOnuOnline((string) $this->onu->pon_port, (string) $this->onu->ONU_id);

            $this->onu->update([
                'optical_signal_db' => $rxPower,
                'tx_power_db' => $txPower,
                'rx_power_db' => $rxPower,
                'operational_state' => $isOnline ? 'online' : 'offline',
                'last_signal_check_at' => now(),
            ]);

            // Threshold checking and alerting
            if ($isOnline && $rxPower !== null && $rxPower < self::CRITICAL_RX_POWER_THRESHOLD) {
                $this->createDegradationIncident($rxPower);
            }

        } catch (Exception $e) {
            Log::warning("Failed to poll ONU signal for ONU {$this->onu->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function createDegradationIncident(float $rxPower): void
    {
        $existingIncident = Incident::where('area_zone', 'ONU ' . $this->onu->serial_number)
            ->where('status', 'open')
            ->first();

        if (!$existingIncident) {
            Incident::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'title' => "Critical Low Optical Signal on ONU {$this->onu->serial_number}",
                'description' => "ONU is reporting an Rx Power of {$rxPower} dBm, which is below the threshold of " . self::CRITICAL_RX_POWER_THRESHOLD . " dBm.",
                'status' => 'open',
                'severity' => 'warning',
                'network_device_id' => $this->onu->olt->networkDevice->id,
                'olt_id' => $this->onu->olt->id,
                'area_zone' => 'ONU ' . $this->onu->serial_number,
            ]);

            // In Phase 11, this would trigger an alert/notification
        }
    }
}
