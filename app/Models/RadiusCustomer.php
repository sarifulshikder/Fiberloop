<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiusCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'subscription_id',
        'created_by',
        'radius_username',
        'radius_password',
        'radius_group',
        'framed_ip_address',
        'framed_ip_netmask',
        'framed_route',
        'session_timeout',
        'idle_timeout',
        'max_input_octets',
        'max_output_octets',
        'max_total_octets',
        'max_download_speed',
        'max_upload_speed',
        'connection_type',
        'is_active',
        'last_auth_at',
        'last_acct_start_at',
        'last_acct_stop_at',
        'nas_ip_address',
        'nas_port',
        'session_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_auth_at' => 'datetime',
        'last_acct_start_at' => 'datetime',
        'last_acct_stop_at' => 'datetime',
        'session_timeout' => 'integer',
        'idle_timeout' => 'integer',
        'max_input_octets' => 'integer',
        'max_output_octets' => 'integer',
        'max_total_octets' => 'integer',
        'max_download_speed' => 'integer',
        'max_upload_speed' => 'integer',
        'radius_password' => 'encrypted',
    ];

    protected $hidden = [
        'radius_password',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
