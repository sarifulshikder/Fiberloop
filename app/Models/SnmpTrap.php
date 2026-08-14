<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SnmpTrap extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'network_device_id',
        'created_by',
        'updated_by',
        'host_ip',
        'udp_port',
        'community_name',
        'snmp_version',
        'description',
        'is_active',
    ];

    protected $casts = [
        'udp_port' => 'integer',
        'is_active' => 'boolean',
        'community_name' => 'encrypted',
    ];

    protected $hidden = [
        'community_name',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class, 'network_device_id', 'id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
