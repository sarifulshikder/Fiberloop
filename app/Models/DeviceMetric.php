<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceMetric extends Model
{
    use HasFactory;

    public $timestamps = false; // We only use created_at, managed manually or by DB default

    protected $fillable = [
        'network_device_id',
        'status',
        'uptime_seconds',
        'cpu_usage_percent',
        'memory_usage_percent',
        'ping_response_ms',
        'interface_stats',
        'additional_data',
        'created_at',
    ];

    protected $casts = [
        'uptime_seconds' => 'integer',
        'cpu_usage_percent' => 'decimal:2',
        'memory_usage_percent' => 'decimal:2',
        'ping_response_ms' => 'integer',
        'interface_stats' => 'array',
        'additional_data' => 'array',
        'created_at' => 'datetime',
    ];

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class);
    }
}
