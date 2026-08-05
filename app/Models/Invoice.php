<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
  use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'customer_id',
        'subscription_id',
        'reseller_id',
        'created_by',
        'updated_by',
        'invoice_number',
        'period_start',
        'period_end',
        'due_date',
        'subtotal',
        'tax_amount',
        'tax_rate',
        'discount_amount',
        'total',
        'paid_amount',
        'outstanding_amount',
        'status',
        'notes',
        'is_prorated',
        'proration_amount',
        'billing_type',
        'sent_at',
        'paid_at',
        'cancelled_at',
        'cancellation_reason',
        'pdf_path',
        'pdf_generated',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'date',
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'tax_rate' => 'integer',
        'discount_amount' => 'integer',
        'total' => 'integer',
        'paid_amount' => 'integer',
        'outstanding_amount' => 'integer',
        'is_prorated' => 'boolean',
        'proration_amount' => 'integer',
        'status' => InvoiceStatus::class,
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'pdf_generated' => 'boolean',
        'notes' => 'string',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
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

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, InvoiceStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', InvoiceStatus::OVERDUE)
            ->where('due_date', '<', now()->toDateString());
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [InvoiceStatus::DRAFT, InvoiceStatus::SENT, InvoiceStatus::OVERDUE, InvoiceStatus::PARTIAL])
            ->where('outstanding_amount', '>', 0);
    }
}
