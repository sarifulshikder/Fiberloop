<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Onu extends Model
{
    use HasFactory;
    use HasUuids;


    protected $fillable = [
        'tenant_id',
        'uuid',
        'olt_id',
        'customer_id',
        'subscription_id',
        'created_by',
        'updated_by',
        'serial_number',
        'mac_address',
        'ONU_id',
        'pon_port',
        'pon_port_name',
        'registration_id',
        'registered_at',
        'is_registered',
        'optical_signal_db',
        'tx_power_db',
        'rx_power_db',
        'vendor_id',
        'firmware_version',
        'hardware_version',
        'ONU_type',
        'is_active',
        'operational_state',
        'last_signal_check_at',
        'distance_meters',
        'configuration',
        'notes',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'is_registered' => 'boolean',
        'optical_signal_db' => 'decimal:2',
        'tx_power_db' => 'decimal:2',
        'rx_power_db' => 'decimal:2',
        'pon_port' => 'integer',
        'is_active' => 'boolean',
        'last_signal_check_at' => 'datetime',
        'distance_meters' => 'integer',
        'configuration' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRegistered($query)
    {
        return $query->where('is_registered', true);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByOlt($query, $oltId)
    {
        return $query->where('olt_id', $oltId);
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
