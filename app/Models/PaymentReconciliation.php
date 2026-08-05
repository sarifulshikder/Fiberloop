<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model for storing payment reconciliation records.
 * Tracks the matching between recorded payments and gateway settlement reports.
 */
class PaymentReconciliation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'payment_id',
        'gateway',
        'gateway_reference',
        'recorded_amount',
        'settlement_amount',
        'settlement_date',
        'status',
        'notes',
        'settlement_data',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'payment_id' => 'integer',
        'recorded_amount' => 'integer',
        'settlement_amount' => 'integer',
        'settlement_date' => 'datetime',
        'status' => ReconciliationStatus::class,
        'gateway' => PaymentMethod::class,
        'resolved_by' => 'integer',
        'resolved_at' => 'datetime',
        'settlement_data' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (PaymentReconciliation $reconciliation) {
            $reconciliation->uuid = $reconciliation->uuid ?? (string) \Str::orderedUuid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope for a specific tenant.
     */
    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope for a specific gateway.
     */
    public function scopeByGateway($query, $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    /**
     * Scope for a specific status.
     */
    public function scopeByStatus($query, ReconciliationStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for unresolved discrepancies.
     */
    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', [ReconciliationStatus::PENDING, ReconciliationStatus::DISCREPANCY]);
    }

    /**
     * Scope for resolved items.
     */
    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * Mark a reconciliation as resolved.
     */
    public function markAsResolved(int $resolvedBy, string $notes = ''): void
    {
        $this->update([
            'resolved_by' => $resolvedBy,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);

        // If the original payment exists, add a note
        if ($this->payment) {
            $this->payment->update([
                'notes' => $this->payment->notes . (empty($this->payment->notes) ? '' : ' | ') . 
                         'Reconciliation resolved: ' . $notes,
            ]);
        }
    }

    /**
     * Get the amount difference between recorded and settlement.
     */
    public function getAmountDifference(): int
    {
        return $this->recorded_amount - $this->settlement_amount;
    }

    /**
     * Check if this reconciliation has an amount discrepancy.
     */
    public function hasAmountDiscrepancy(): bool
    {
        return $this->recorded_amount !== $this->settlement_amount;
    }
}
