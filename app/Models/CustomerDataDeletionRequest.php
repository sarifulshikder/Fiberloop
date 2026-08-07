<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDataDeletionRequest extends Model
{
    use HasFactory;
    use HasUuids;


    protected $fillable = [
        'uuid',
        'customer_id',
        'processed_by_admin',
        'status',
        'scope',
        'confirmation_required',
        'confirmation_token',
        'confirmation_sent_at',
        'confirmation_confirmed_at',
        'deletion_report',
        'requested_at',
        'scheduled_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'status' => 'string',
        'scope' => 'string',
        'confirmation_required' => 'boolean',
        'confirmation_sent_at' => 'datetime',
        'confirmation_confirmed_at' => 'datetime',
        'deletion_report' => 'array',
        'requested_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? \Illuminate\Support\Str::uuid();
            $model->requested_at = $model->requested_at ?? now();
            $model->confirmation_token = $model->confirmation_token ?? \Illuminate\Support\Str::random(64);
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_admin');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmationRequired($query)
    {
        return $query->where('status', 'confirmation_required');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function isConfirmationRequired(): bool
    {
        return $this->confirmation_required &&
               $this->status === 'confirmation_required' &&
               !$this->confirmation_confirmed_at;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmation_confirmed_at !== null;
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
