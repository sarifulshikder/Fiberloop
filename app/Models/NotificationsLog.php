<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class NotificationsLog extends Model
{
    use BelongsToTenant;

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
        'data' => 'array',
        'metadata' => 'array',
        'sent' => 'boolean',
        'delivered' => 'boolean',
        'failed' => 'boolean',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];
}
