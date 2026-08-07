<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResellerApprovalRequest extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;


    protected $fillable = [
        'uuid',
        'tenant_id',
        'reseller_id',
        'requested_by',
        'reviewed_by',
        'type',
        'payload',
        'status',
        'rejection_reason',
        'expires_at',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
