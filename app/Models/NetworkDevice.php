<?php

namespace App\Models;

use App\Enums\DeviceVendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkDevice extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'created_by',
        'updated_by',
        'name',
        'vendor',
        'model',
        'serial_number',
        'ip_address',
        'hostname',
        'port',
        'username',
        'password',
        'snmp_community',
        'snmp_version',
        'location',
        'latitude',
        'longitude',
        'address',
        'is_active',
        'last_checked_at',
        'is_reachable',
        'capabilities',
        'configuration',
        'notes',
    ];

    protected $casts = [
        'vendor' => DeviceVendor::class,
        'port' => 'integer',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
        'is_reachable' => 'boolean',
        'capabilities' => 'array',
        'configuration' => 'array',
        'password' => 'encrypted',
        'snmp_community' => 'encrypted',
    ];

    protected $hidden = [
        'password',
        'snmp_community',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * MikroTikService reads ->api_port; this aliases the 'port' column.
     */
    public function getApiPortAttribute(): int
    {
        return $this->port ?? 8728;
    }

    public function olts(): HasMany
    {
        return $this->hasMany(Olt::class);
    }

    public function deviceMetrics(): HasMany
    {
        return $this->hasMany(DeviceMetric::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function ipPools(): HasMany
    {
        return $this->hasMany(IpPool::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByVendor($query, DeviceVendor $vendor)
    {
        return $query->where('vendor', $vendor);
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
