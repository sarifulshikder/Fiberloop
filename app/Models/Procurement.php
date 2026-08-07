<?php

namespace App\Models;

use App\Enums\ProcurementStatus;
use App\Models\Scopes\ResellerScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Procurement extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;


    protected static function booted(): void
    {
        static::addGlobalScope(new ResellerScope());
    }

    protected $fillable = [
        'tenant_id',
        'uuid',
        'po_number',
        'supplier_id',
        'created_by',
        'approved_by',
        'updated_by',
        'title',
        'description',
        'status',
        'priority',
        'order_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'approved_at',
        'subtotal',
        'tax_amount',
        'shipping_cost',
        'total_amount',
        'currency',
        'tracking_number',
        'shipping_method',
        'notes',
    ];

    protected $casts = [
        'status' => ProcurementStatus::class,
        'priority' => 'string',
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'approved_at' => 'date',
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'shipping_cost' => 'integer',
        'total_amount' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class, 'procurement_items')
            ->withPivot('quantity', 'unit_price', 'total_price', 'received_quantity', 'status')
            ->withTimestamps();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'reference_number', 'po_number');
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, ProcurementStatus $status)
    {
        return $query->where('status', $status->value);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', ProcurementStatus::PENDING_APPROVAL->value);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', ProcurementStatus::RECEIVED->value)
            ->where('status', '!=', ProcurementStatus::CANCELLED->value)
            ->whereDate('expected_delivery_date', '<', now()->toDateString());
    }

    public function scopeRecentlyCreated($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days)->toDateString())
            ->orderBy('created_at', 'desc');
    }

    public function isOverdue(): bool
    {
        if ($this->status === ProcurementStatus::RECEIVED || $this->status === ProcurementStatus::CANCELLED) {
            return false;
        }
        return $this->expected_delivery_date && $this->expected_delivery_date->isBefore(now());
    }

    public function getTotalAmountInTakaAttribute(): float
    {
        return $this->total_amount / 100;
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
