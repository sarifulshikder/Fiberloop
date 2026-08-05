<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPricingOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'subscription_id',
        'created_by',
        'updated_by',
        'override_price',
        'override_installation_fee',
        'override_security_deposit',
        'reason',
        'is_active',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'override_price' => 'integer',
        'override_installation_fee' => 'integer',
        'override_security_deposit' => 'integer',
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SubscriptionPricingOverride $override) {
            $override->uuid = $override->uuid ?? (string) \Str::orderedUuid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
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
        return $query->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', now());
            });
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForSubscription($query, $subscriptionId)
    {
        return $query->where('subscription_id', $subscriptionId);
    }

    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->start_date && $this->start_date->isAfter(now())) {
            return false;
        }

        if ($this->end_date && $this->end_date->isBefore(now())) {
            return false;
        }

        return true;
    }
}