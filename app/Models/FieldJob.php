<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldJob extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    use \Stancl\Tenancy\Database\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'ticket_id',
        'customer_id',
        'assigned_to',
        'type',
        'status',
        'address',
        'geo_lat',
        'geo_lng',
        'scheduled_at',
        'started_at',
        'completed_at',
        'technician_notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function ticket(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedTo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function equipment(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class, 'field_job_equipment')
                    ->withPivot('status', 'notes')
                    ->withTimestamps();
    }
}
