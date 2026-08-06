<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Models\Scopes\ResellerScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new ResellerScope());
    }

    use HasFactory;
    use SoftDeletes;


    protected $fillable = [
        'tenant_id',
        'uuid',
        'customer_id',
        'package_id',
        'reseller_id',
        'created_by',
        'updated_by',
        'start_date',
        'end_date',
        'next_billing_date',
        'status',
        'monthly_price',
        'billing_cycle_discount',
        'final_price',
        'is_prorated',
        'proration_amount',
        'proration_notes',
        'assigned_ip',
        'assigned_mac',
        'assigned_port',
        'assigned_vlan',
        'network_device_id',
        'olt_id',
        'onu_id',
        'activated_at',
        'expired_at',
        'cancelled_at',
        'suspended_at',
        'cancellation_reason',
        'suspension_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_billing_date' => 'date',
        'status' => SubscriptionStatus::class,
        'monthly_price' => 'integer',
        'billing_cycle_discount' => 'integer',
        'final_price' => 'integer',
        'is_prorated' => 'boolean',
        'proration_amount' => 'integer',
        'activated_at' => 'datetime',
        'expired_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class);
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function onu(): BelongsTo
    {
        return $this->belongsTo(Onu::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function radiusCustomer(): HasMany
    {
        return $this->hasMany(RadiusCustomer::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', SubscriptionStatus::ACTIVE);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, SubscriptionStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeNextBillingDue($query, $days = 7)
    {
        return $query->where('next_billing_date', '<=', now()->addDays($days)->toDateString());
    }
}
