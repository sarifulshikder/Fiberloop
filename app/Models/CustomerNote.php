<?php

namespace App\Models;

use App\Enums\NoteType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerNote extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'customer_id',
        'created_by',
        'updated_by',
        'type',
        'content',
        'title',
        'category',
        'reference_type',
        'reference_id',
        'is_internal',
        'is_important',
    ];

    protected $casts = [
        'type' => NoteType::class,
        'is_internal' => 'boolean',
        'is_important' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Polymorphic relationship for reference (e.g., ticket, invoice, etc.)
    // This allows notes to be linked to different models
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeImportant($query)
    {
        return $query->where('is_important', true);
    }

    public function scopeByType($query, NoteType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // Helper methods
    public function isInternal(): bool
    {
        return $this->is_internal;
    }

    public function isImportant(): bool
    {
        return $this->is_important;
    }

    public function getCategoryLabelAttribute(): string
    {
        $categories = [
            'call' => 'Call',
            'complaint' => 'Complaint',
            'technician_visit' => 'Technician Visit',
            'payment' => 'Payment',
            'support' => 'Support',
            'sales' => 'Sales',
            'other' => 'Other',
        ];

        return $categories[$this->category] ?? $this->category;
    }
}
