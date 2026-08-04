<?php

namespace App\Models;

use App\Enums\ConnectionType;
use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
  use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'created_by',
        'updated_by',
        'first_name',
        'last_name',
        'email',
        'phone',
        'alternate_phone',
        'date_of_birth',
        'gender',
        'nid_number',
        'nid_front_photo',
        'nid_back_photo',
        'signature_photo',
        'service_address',
        'service_latitude',
        'service_longitude',
        'billing_address',
        'connection_type',
        'radius_username',
        'radius_password',
        'static_ip',
        'mac_address',
        'status',
        'activated_at',
        'suspended_at',
        'terminated_at',
        'suspension_reason',
        'termination_reason',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'connection_type' => ConnectionType::class,
        'status' => CustomerStatus::class,
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
        'terminated_at' => 'datetime',
        'notes' => 'array',
    ];

    protected $appends = [
        'full_name',
    ];

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function radiusCustomer(): HasMany
    {
        return $this->hasMany(RadiusCustomer::class);
    }

    public function onus(): HasMany
    {
        return $this->hasMany(Onu::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', CustomerStatus::ACTIVE);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, CustomerStatus $status)
    {
        return $query->where('status', $status);
    }
}
