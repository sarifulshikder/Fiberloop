<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AddOn extends Model
{
    use HasFactory;
    use HasUuids;


    protected $fillable = [
        'tenant_id',
        'uuid',
        'created_by',
        'updated_by',
        'name',
        'code',
        'description',
        'type',
        'price',
        'billing_cycle',
        'configuration',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'integer',
        'configuration' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (AddOn $addOn) {
            $addOn->uuid = $addOn->uuid ?? (string) \Str::orderedUuid();
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

    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Subscription::class, 'subscription_add_ons', 'add_on_id', 'subscription_id')
            ->withPivot(['custom_price', 'start_date', 'end_date', 'is_active']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Add enum for billing cycle
    public static function getBillingCycles(): array
    {
        return ['one_time', 'monthly', 'quarterly', 'annual'];
    }

    public static function getTypes(): array
    {
        return ['static_ip', 'extra_device_slot', 'ott_iptv', 'voice', 'other'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
