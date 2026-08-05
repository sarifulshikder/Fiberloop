<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tax rate configuration per tenant.
 * Stores VAT/tax rates for different jurisdictions or customer types.
 * All amounts are percentages (e.g., 15 = 15%).
 */
class TaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'rate',
        'description',
        'is_active',
        'is_default',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'rate' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    /**
     * Get the current tax rate for a tenant.
     */
    public static function getCurrentRateForTenant(int $tenantId): int
    {
        $rate = static::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now()->toDateString());
            })
            ->orderBy('effective_from', 'desc')
            ->first();
        
        return $rate ? $rate->rate : config('billing.tax_rate', 15);
    }

    /**
     * Get the default tax rate for a tenant.
     */
    public static function getDefaultRateForTenant(int $tenantId): int
    {
        $rate = static::query()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();
        
        return $rate ? $rate->rate : config('billing.tax_rate', 15);
    }

    /**
     * Get the current global default tax rate.
     */
    public static function getGlobalDefaultRate(): int
    {
        $rate = static::query()
            ->whereNull('tenant_id')
            ->where('is_default', true)
            ->first();
        
        return $rate ? $rate->rate : config('billing.tax_rate', 15);
    }

    /**
     * Calculate tax amount from a base amount.
     */
    public function calculateTax(int $baseAmount): int
    {
        return (int) round($baseAmount * $this->rate / 100);
    }

    /**
     * Calculate total with tax from a base amount.
     */
    public function calculateTotal(int $baseAmount): int
    {
        return $baseAmount + $this->calculateTax($baseAmount);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
