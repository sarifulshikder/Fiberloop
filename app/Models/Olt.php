<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Olt extends Model
{
    use HasFactory;
    use HasUuids;


    protected $fillable = [
        'tenant_id',
        'uuid',
        'network_device_id',
        'created_by',
        'updated_by',
        'name',
        'chassis_id',
        'firmware_version',
        'hardware_version',
        'uptime',
        'total_pon_ports',
        'used_pon_ports',
        'max_onus_per_pon',
        'rack',
        'slot',
        'location_notes',
        'is_active',
        'last_sync_at',
        'configuration',
        'notes',
    ];

    protected $casts = [
        'total_pon_ports' => 'integer',
        'used_pon_ports' => 'integer',
        'max_onus_per_pon' => 'integer',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
        'configuration' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function onus(): HasMany
    {
        return $this->hasMany(Onu::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
