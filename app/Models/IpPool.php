<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class IpPool extends Model
{
    use HasFactory;
    use HasUuids;
    use BelongsToTenant;


    protected $fillable = [
        'tenant_id',
        'uuid',
        'name',
        'type',
        'subnet',
        'gateway',
        'dns1',
        'dns2',
        'network_device_id',
        'status',
        'created_by',
        'updated_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function networkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class);
    }

    public function ipAddresses(): HasMany
    {
        return $this->hasMany(IpAddress::class);
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
