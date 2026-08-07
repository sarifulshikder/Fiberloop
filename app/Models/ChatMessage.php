<?php

namespace App\Models;

use App\Models\Scopes\ResellerScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
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
        'conversation_id',
        'sender_id',
        'sender_type',
        'content',
        'attachment_path',
        'attachment_type',
        'is_read',
        'read_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeFromCustomer($query)
    {
        return $query->where('sender_type', 'customer');
    }

    public function scopeFromAgent($query)
    {
        return $query->where('sender_type', 'agent');
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
