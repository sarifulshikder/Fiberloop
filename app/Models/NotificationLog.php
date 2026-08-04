<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
  use HasFactory;

    protected $table = 'notifications_log';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'user_id',
        'notifiable_type',
        'notifiable_id',
        'type',
        'channel',
        'subject',
        'message',
        'data',
        'to_phone',
        'to_email',
        'to_device_token',
        'sent',
        'delivered',
        'failed',
        'sent_at',
        'delivered_at',
        'gateway_response',
        'gateway_reference',
        'error_message',
        'template_used',
        'attempt_count',
        'metadata',
    ];

    protected $casts = [
        'sent' => 'boolean',
        'delivered' => 'boolean',
        'failed' => 'boolean',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'data' => 'array',
        'metadata' => 'array',
        'attempt_count' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeSent($query)
    {
        return $query->where('sent', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('failed', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }
}
