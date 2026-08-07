<?php

namespace App\Models;

use App\Enums\CreditNoteStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditNote extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;


    protected $fillable = [
        'tenant_id',
        'uuid',
        'customer_id',
        'invoice_id',
        'created_by',
        'approved_by',
        'credit_note_number',
        'reason',
        'issue_date',
        'subtotal',
        'tax_amount',
        'total',
        'status',
        'approved_at',
        'applied_at',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'total' => 'integer',
        'status' => CreditNoteStatus::class,
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
        'notes' => 'string',
    ];

    protected static function booted(): void
    {
        static::creating(function (CreditNote $creditNote) {
            $creditNote->uuid = $creditNote->uuid ?? (string) \Str::orderedUuid();
            $creditNote->issue_date = $creditNote->issue_date ?? now()->toDateString();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
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

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, CreditNoteStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
