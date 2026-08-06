<?php

namespace App\Models;

use App\Enums\ResellerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reseller extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'parent_id',
        'created_by',
        'updated_by',
        'name',
        'code',
        'email',
        'phone',
        'alternate_phone',
        'address',
        'status',
        'commission_rate',
        'commission_amount',
        'wallet_balance',
        'total_earnings',
        'total_withdrawn',
        'contract_start_date',
        'contract_end_date',
        'contract_terms',
        'activated_at',
        'suspended_at',
        'terminated_at',
        'suspension_reason',
        'termination_reason',
        'notes',
    ];

    protected $casts = [
        'status' => ResellerStatus::class,
        'commission_rate' => 'integer',
        'commission_amount' => 'integer',
        'wallet_balance' => 'integer',
        'total_earnings' => 'integer',
        'total_withdrawn' => 'integer',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
        'terminated_at' => 'datetime',
        'notes' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Reseller::class, 'parent_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
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

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function commissionLedger(): HasMany
    {
        return $this->hasMany(ResellerCommissionLedger::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ResellerApprovalRequest::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', ResellerStatus::ACTIVE);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Wallet balance formatted as BDT (for display) */
    public function getWalletBalanceBdtAttribute(): string
    {
        return '৳' . number_format($this->wallet_balance / 100, 2);
    }

    /** Total commission earned formatted as BDT */
    public function getTotalEarningsBdtAttribute(): string
    {
        return '৳' . number_format($this->total_earnings / 100, 2);
    }
}
