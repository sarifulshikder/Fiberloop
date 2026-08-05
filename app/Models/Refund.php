<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'payment_id',
        'customer_id',
        'invoice_id',
        'created_by',
        'approved_by',
        'processed_by',
        'refund_number',
        'reason',
        'request_date',
        'processed_date',
        'amount',
        'fee_amount',
        'net_amount',
        'status',
        'gateway_reference',
        'gateway_response',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'request_date' => 'date',
        'processed_date' => 'date',
        'amount' => 'integer',
        'fee_amount' => 'integer',
        'net_amount' => 'integer',
        'status' => RefundStatus::class,
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Refund $refund) {
            $refund->uuid = $refund->uuid ?? (string) \Str::orderedUuid();
            $refund->request_date = $refund->request_date ?? now()->toDateString();
            $refund->net_amount = $refund->net_amount ?: ($refund->amount - $refund->fee_amount);
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, RefundStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPayment($query, $paymentId)
    {
        return $query->where('payment_id', $paymentId);
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
