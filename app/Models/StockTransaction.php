<?php

namespace App\Models;

use App\Enums\StockTransactionReason;
use App\Enums\StockTransactionType;
use App\Models\Scopes\ResellerScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransaction extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new ResellerScope());
    }

    protected $fillable = [
        'tenant_id',
        'uuid',
        'inventory_item_id',
        'user_id',
        'customer_id',
        'field_job_id',
        'subscription_id',
        'created_by',
        'updated_by',
        'transaction_type',
        'reason',
        'reference_number',
        'notes',
        'previous_status',
        'previous_location',
        'previous_holder_id',
        'new_status',
        'new_location',
        'new_holder_id',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'transaction_type' => StockTransactionType::class,
        'reason' => StockTransactionReason::class,
        'previous_status' => InventoryStatus::class,
        'new_status' => InventoryStatus::class,
        'quantity' => 'integer',
        'unit_cost' => 'integer',
        'total_cost' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function fieldJob(): BelongsTo
    {
        return $this->belongsTo(FieldJob::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function previousHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_holder_id');
    }

    public function newHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_holder_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByItem($query, $itemId)
    {
        return $query->where('inventory_item_id', $itemId);
    }

    public function scopeByType($query, StockTransactionType $type)
    {
        return $query->where('transaction_type', $type->value);
    }

    public function scopeByReason($query, StockTransactionReason $reason)
    {
        return $query->where('reason', $reason->value);
    }

    public function scopeIncoming($query)
    {
        return $query->whereIn('transaction_type', [
            StockTransactionType::RECEIPT->value,
            StockTransactionType::RETURN->value,
            StockTransactionType::ADJUSTMENT->value,
        ]);
    }

    public function scopeOutgoing($query)
    {
        return $query->whereIn('transaction_type', [
            StockTransactionType::ISSUE->value,
            StockTransactionType::TRANSFER->value,
            StockTransactionType::RETIREMENT->value,
            StockTransactionType::DISPOSAL->value,
        ]);
    }

    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    public function isIncoming(): bool
    {
        return $this->transaction_type->isIncoming();
    }

    public function isOutgoing(): bool
    {
        return $this->transaction_type->isOutgoing();
    }
}
