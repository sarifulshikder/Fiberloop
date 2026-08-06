<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Scopes\ResellerScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected static function booted(): void
    {
        static::addGlobalScope(new ResellerScope());
    }

    protected $fillable = [
        'tenant_id',
        'uuid',
        'invoice_id',
        'customer_id',
        'reseller_id',
        'created_by',
        'updated_by',
        'collected_by',
        'amount',
        'fee_amount',
        'net_amount',
        'method',
        'status',
        'gateway_reference',
        'gateway_response',
        'paid_at',
        'notes',
        'failure_reason',
        'receipt_path',
        'split_from_payment_id',
        'is_partial',
        'is_wallet_topup',
        'applied_to_invoice',
    ];

    protected $casts = [
        'amount' => 'integer',
        'fee_amount' => 'integer',
        'net_amount' => 'integer',
        'method' => PaymentMethod::class,
        'status' => PaymentStatus::class,
        'paid_at' => 'datetime',
        'is_partial' => 'boolean',
        'is_wallet_topup' => 'boolean',
        'applied_to_invoice' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function parentPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'split_from_payment_id');
    }

    public function childPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'split_from_payment_id');
    }

    /**
     * Scope for partial payments.
     */
    public function scopePartial($query)
    {
        return $query->where('is_partial', true);
    }

    /**
     * Scope for wallet topups.
     */
    public function scopeWalletTopups($query)
    {
        return $query->where('is_wallet_topup', true);
    }

    /**
     * Scope for split payments (payments that have been split from a parent).
     */
    public function scopeSplitFromPayment($query, $parentId)
    {
        return $query->where('split_from_payment_id', $parentId);
    }
}
