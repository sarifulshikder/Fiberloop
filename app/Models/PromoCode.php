<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PromoCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'created_by',
        'updated_by',
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'applies_to',
        'start_date',
        'end_date',
        'max_uses',
        'uses_count',
        'max_uses_per_customer',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
        'max_uses_per_customer' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (PromoCode $promoCode) {
            $promoCode->uuid = $promoCode->uuid ?? (string) \Str::orderedUuid();
        });
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

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_promo_code', 'promo_code_id', 'package_id');
    }

    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Subscription::class, 'subscription_promo_code', 'promo_code_id', 'subscription_id');
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

    public function canBeUsed(): bool
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

        if ($this->max_uses && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function getDiscountAmount(int $originalPrice): int
    {
        return match ($this->discount_type) {
            'percentage' => (int) ($originalPrice * $this->discount_value / 100),
            'fixed_amount' => (int) $this->discount_value,
            'fixed_price' => (int) min($this->discount_value, $originalPrice),
            default => 0,
        };
    }

    public function getDiscountedPrice(int $originalPrice): int
    {
        return $originalPrice - $this->getDiscountAmount($originalPrice);
    }
}
