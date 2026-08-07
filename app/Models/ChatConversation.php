<?php

namespace App\Models;

use App\Models\Scopes\ResellerScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatConversation extends Model
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
        'customer_id',
        'assigned_to',
        'subject',
        'status',
        'priority',
        'source',
        'is_read_by_customer',
        'is_read_by_agent',
        'resolved_at',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_read_by_customer' => 'boolean',
        'is_read_by_agent' => 'boolean',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'latest_message_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
