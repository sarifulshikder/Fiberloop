<?php

namespace App\Models;

use App\Enums\ProcurementItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'procurement_id',
        'inventory_item_id',
        'item_type',
        'category',
        'brand',
        'model',
        'serial_number',
        'mac_address',
        'quantity',
        'unit_price',
        'total_price',
        'status',
        'received_quantity',
    ];

    protected $casts = [
        'status' => ProcurementItemStatus::class,
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'total_price' => 'integer',
        'received_quantity' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByProcurement($query, $procurementId)
    {
        return $query->where('procurement_id', $procurementId);
    }

    public function scopeByStatus($query, ProcurementItemStatus $status)
    {
        return $query->where('status', $status->value);
    }

    public function scopePending($query)
    {
        return $query->where('status', ProcurementItemStatus::PENDING->value);
    }

    public function scopeReceived($query)
    {
        return $query->where('status', ProcurementItemStatus::RECEIVED->value);
    }

    public function isFullyReceived(): bool
    {
        return $this->received_quantity >= $this->quantity;
    }

    public function getRemainingQuantityAttribute(): int
    {
        return $this->quantity - $this->received_quantity;
    }

    public function getTotalPriceInTakaAttribute(): float
    {
        return $this->total_price / 100;
    }

    public function getUnitPriceInTakaAttribute(): float
    {
        return $this->unit_price / 100;
    }
}
