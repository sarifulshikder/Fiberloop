<?php

namespace App\Models;

use App\Enums\BillingType;
use App\Enums\PackageBillingCycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
  use HasFactory;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'created_by',
        'updated_by',
        'name',
        'description',
        'code',
        'download_speed',
        'upload_speed',
        'fup_threshold',
        'fup_throttled_download',
        'fup_throttled_upload',
        'price',
        'billing_cycle',
        'billing_type',
        'installation_fee',
        'security_deposit',
        'tax_rate',
        'is_active',
        'is_popular',
        'sort_order',
        'features',
    ];

    protected $casts = [
        'download_speed' => 'integer',
        'upload_speed' => 'integer',
        'fup_threshold' => 'integer',
        'fup_throttled_download' => 'integer',
        'fup_throttled_upload' => 'integer',
        'price' => 'integer',
        'billing_cycle' => PackageBillingCycle::class,
        'billing_type' => BillingType::class,
        'installation_fee' => 'integer',
        'security_deposit' => 'integer',
        'tax_rate' => 'integer',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
        'sort_order' => 'integer',
        'features' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Package $package) {
            $package->uuid = $package->uuid ?? (string) \Str::orderedUuid();
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
}
