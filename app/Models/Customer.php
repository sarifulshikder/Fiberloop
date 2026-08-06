<?php

namespace App\Models;

use App\Enums\ConnectionType;
use App\Enums\CustomerStatus;
use App\Models\Scopes\ResellerScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new ResellerScope());
    }

    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'lead_id',
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
        'area',
        'zone',
        'connection_type',
        'radius_username',
        'radius_password',
        'static_ip',
        'mac_address',
        'status',
        'wallet_balance',
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
        'wallet_balance' => 'integer',
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
        return $this->belongsTo(
            Tenant::class,
            'tenant_id',
            'lead_id',
            'id'
        );
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

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    public function packageChangeRequests(): HasMany
    {
        return $this->hasMany(PackageChangeRequest::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', CustomerStatus::ACTIVE);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where(
            'tenant_id',
            'lead_id',
            $tenantId
        );
    }

    public function scopeByStatus($query, CustomerStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByArea($query, $area)
    {
        return $query->where('area', $area);
    }

    public function scopeByZone($query, $zone)
    {
        return $query->where('zone', $zone);
    }

    public function scopePending($query)
    {
        return $query->where('status', CustomerStatus::PENDING);
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', CustomerStatus::SUSPENDED);
    }

    public function scopeTerminated($query)
    {
        return $query->where('status', CustomerStatus::TERMINATED);
    }
}
