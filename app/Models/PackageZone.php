<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'package_id',
        'zone',
        'area',
        'created_by',
        'updated_by',
        'is_available',
        'custom_price',
        'max_connections',
        'current_connections',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'custom_price' => 'integer',
        'max_connections' => 'integer',
        'current_connections' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeForZone($query, $zone)
    {
        return $query->where('zone', $zone);
    }

    public function scopeForArea($query, $area)
    {
        return $query->where('area', $area);
    }

    public function scopeForPackage($query, $packageId)
    {
        return $query->where('package_id', $packageId);
    }

    public function hasCapacity(): bool
    {
        if ($this->max_connections === null) {
            return true; // No limit
        }
        return $this->current_connections < $this->max_connections;
    }

    public function getEffectivePrice(): int
    {
        return $this->custom_price ?? $this->package->price;
    }
}
