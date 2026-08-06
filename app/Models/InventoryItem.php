<?php

namespace App\Models;

use App\Enums\InventoryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'customer_id',
        'subscription_id',
        'reseller_id',
        'created_by',
        'updated_by',
        'name',
        'item_type',
        'category',
        'brand',
        'model',
        'serial_number',
        'imei',
        'mac_address',
        'barcode',
        'asset_tag',
        'status',
        'warehouse',
        'bin_location',
        'assigned_location',
        'purchase_price',
        'selling_price',
        'purchase_date',
        'purchase_invoice_id',
        'supplier_id',
        'warranty_start',
        'warranty_end',
        'warranty_months',
        'assigned_at',
        'returned_at',
        'assignment_notes',
        'condition',
        'condition_notes',
        'specifications',
        'notes',
    ];

    protected $casts = [
        'item_type' => 'string',
        'status' => InventoryStatus::class,
        'purchase_price' => 'integer',
        'selling_price' => 'integer',
        'purchase_date' => 'date',
        'warranty_start' => 'date',
        'warranty_end' => 'date',
        'warranty_months' => 'integer',
        'assigned_at' => 'datetime',
        'returned_at' => 'datetime',
        'specifications' => 'array',
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

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'purchase_invoice_id');
    }

    public function scopeInStock($query)
    {
        return $query->where('status', InventoryStatus::IN_STOCK);
    }

    public function scopeAssigned($query)
    {
        return $query->where('status', InventoryStatus::ASSIGNED);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('item_type', $type);
    }

    public function scopeWarrantyExpiring($query, $days = 30)
    {
        return $query->where('warranty_end', '<=', now()->addDays($days)->toDateString())
            ->where('warranty_end', '>=', now()->toDateString());
    }
}
