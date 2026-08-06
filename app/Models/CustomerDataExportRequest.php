<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDataExportRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'customer_id',
        'requested_by_admin',
        'status',
        'requested_data_types',
        'format',
        'download_url',
        'download_expires_at',
        'requested_at',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'status' => 'string',
        'requested_data_types' => 'array',
        'format' => 'string',
        'download_expires_at' => 'datetime',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? \Illuminate\Support\Str::uuid();
            $model->requested_at = $model->requested_at ?? now();
            $model->requested_data_types = $model->requested_data_types ?? ['profile', 'subscriptions', 'invoices', 'payments'];
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_admin');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
            ->orWhere(function ($query) {
                $query->where('status', 'completed')
                    ->where('download_expires_at', '<', now());
            });
    }

    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if ($this->status === 'completed' && $this->download_expires_at) {
            return $this->download_expires_at->isPast();
        }

        return false;
    }
}
