<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable ledger entry for reseller wallet movements.
 * Every credit and debit is recorded here — never hard-deleted.
 */
class ResellerCommissionLedger extends Model
{
    public const UPDATED_AT = null; // Immutable — no updated_at

    protected $table = 'reseller_commission_ledger';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'reseller_id',
        'invoice_id',
        'payment_id',
        'created_by',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'created_at' => 'datetime',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Format amount as BDT string for display */
    public function getAmountBdtAttribute(): string
    {
        return '৳' . number_format($this->amount / 100, 2);
    }
}
