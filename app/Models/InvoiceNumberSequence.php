<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceNumberSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'last_invoice_number',
        'last_credit_note_number',
        'last_refund_number',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'last_invoice_number' => 'integer',
        'last_credit_note_number' => 'integer',
        'last_refund_number' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
