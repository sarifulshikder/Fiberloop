<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OltPort extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'olt_id',
        'network_device_id',
        'if_index',
        'name',
        'alias',
        'if_type',
        'type_label',
        'admin_status',
        'oper_status',
        'speed',
        'high_speed',
        'mtu',
        'mac_address',
        'is_uplink',
        'is_pon',
        'is_active',
        'sfp_present',
        'sfp_vendor',
        'sfp_part_number',
        'sfp_serial_number',
        'sfp_revision',
        'sfp_date_code',
        'sfp_connector_type',
        'sfp_transceiver_code',
        'sfp_encoding',
        'sfp_wavelength',
        'sfp_distance',
        'sfp_standard',
        'sfp_tx_power_dbm',
        'sfp_rx_power_dbm',
        'sfp_temperature_c',
        'sfp_voltage_v',
        'sfp_tx_bias_ma',
        'sfp_rx_power_mw',
        'sfp_tx_power_mw',
        'sfp_thresholds',
        'sfp_alarms',
        'sfp_warnings',
        'if_in_octets',
        'if_out_octets',
        'if_in_errors',
        'if_out_errors',
        'if_in_discards',
        'if_out_discards',
        'if_in_ucast_pkts',
        'if_out_ucast_pkts',
        'if_last_change',
        'link_up_since',
        'uptime_string',
        'last_polled_at',
        'poll_error',
        'poll_error_message',
    ];

    protected $casts = [
        'if_index' => 'integer',
        'if_type' => 'integer',
        'admin_status' => 'integer',
        'oper_status' => 'integer',
        'speed' => 'integer',
        'high_speed' => 'integer',
        'mtu' => 'integer',
        'is_uplink' => 'boolean',
        'is_pon' => 'boolean',
        'is_active' => 'boolean',
        'sfp_present' => 'boolean',
        'sfp_tx_power_dbm' => 'decimal:2',
        'sfp_rx_power_dbm' => 'decimal:2',
        'sfp_temperature_c' => 'decimal:2',
        'sfp_voltage_v' => 'decimal:3',
        'sfp_tx_bias_ma' => 'decimal:2',
        'sfp_rx_power_mw' => 'decimal:4',
        'sfp_tx_power_mw' => 'decimal:4',
        'sfp_thresholds' => 'array',
        'sfp_alarms' => 'array',
        'sfp_warnings' => 'array',
        'if_in_octets' => 'integer',
        'if_out_octets' => 'integer',
        'if_in_errors' => 'integer',
        'if_out_errors' => 'integer',
        'if_in_discards' => 'integer',
        'if_out_discards' => 'integer',
        'if_in_ucast_pkts' => 'integer',
        'if_out_ucast_pkts' => 'integer',
        'if_last_change' => 'integer',
        'link_up_since' => 'datetime',
        'last_polled_at' => 'datetime',
        'poll_error' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeUplinks($query)
    {
        return $query->where('is_uplink', true);
    }

    public function scopePonPorts($query)
    {
        return $query->where('is_pon', true);
    }

    public function scopeByOlt($query, $oltId)
    {
        return $query->where('olt_id', $oltId);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // Helper accessors
    public function getOperStatusLabelAttribute(): string
    {
        return match ($this->oper_status) {
            1 => 'Up',
            2 => 'Down',
            3 => 'Testing',
            4 => 'Unknown',
            5 => 'Dormant',
            6 => 'Not Present',
            7 => 'Lower Layer Down',
            default => 'Unknown',
        };
    }

    public function getAdminStatusLabelAttribute(): string
    {
        return match ($this->admin_status) {
            1 => 'Up',
            2 => 'Down',
            3 => 'Testing',
            default => 'Unknown',
        };
    }

    public function getSpeedLabelAttribute(): string
    {
        if ($this->high_speed) {
            return $this->high_speed >= 1000 ? ($this->high_speed / 1000) . 'G' : $this->high_speed . 'M';
        }
        if ($this->speed) {
            $g = $this->speed / 1_000_000_000;
            $m = $this->speed / 1_000_000;
            return $g >= 1 ? round($g, 1) . 'G' : round($m) . 'M';
        }
        return '—';
    }

    public function getRxPowerColorAttribute(): ?string
    {
        if ($this->sfp_rx_power_dbm === null) {
            return null;
        }
        $val = $this->sfp_rx_power_dbm;
        if ($val < -27) {
            return 'danger';
        }
        if ($val < -24) {
            return 'warning';
        }
        return 'success';
    }

    public function getTxPowerColorAttribute(): ?string
    {
        if ($this->sfp_tx_power_dbm === null) {
            return null;
        }
        $val = $this->sfp_tx_power_dbm;
        if ($val > 3 || $val < -10) {
            return 'danger';
        } // Typical SFP Tx range: -10 to +3 dBm
        if ($val > 2 || $val < -8) {
            return 'warning';
        }
        return 'success';
    }

    public function getTemperatureColorAttribute(): ?string
    {
        if ($this->sfp_temperature_c === null) {
            return null;
        }
        $val = $this->sfp_temperature_c;
        if ($val > 75) {
            return 'danger';
        }
        if ($val > 65) {
            return 'warning';
        }
        return 'success';
    }

    public function getVoltageColorAttribute(): ?string
    {
        if ($this->sfp_voltage_v === null) {
            return null;
        }
        $val = $this->sfp_voltage_v;
        if ($val < 3.0 || $val > 3.6) {
            return 'danger';
        } // Typical 3.3V ±0.3V
        if ($val < 3.1 || $val > 3.5) {
            return 'warning';
        }
        return 'success';
    }

    public function getHasAlarmsAttribute(): bool
    {
        return !empty($this->sfp_alarms);
    }

    public function getHasWarningsAttribute(): bool
    {
        return !empty($this->sfp_warnings);
    }

    public function getUtilizationPercentAttribute(): ?float
    {
        if (!$this->high_speed || !$this->if_in_octets || !$this->if_out_octets) {
            return null;
        }
        // Rough estimate: (in + out) bits per second / (speed * 2 for full duplex) * 100
        // This would need time-delta calculation for accuracy
        return null; // Implement with counter deltas in a job
    }
}
