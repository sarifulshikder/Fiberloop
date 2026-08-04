<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
  use HasFactory;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'customer_id',
        'subscription_id',
        'created_by',
        'assigned_to',
        'updated_by',
        'ticket_number',
        'subject',
        'description',
        'category',
        'sub_category',
        'priority',
        'status',
        'due_at',
        'resolved_at',
        'closed_at',
        'response_time_minutes',
        'resolution_time_minutes',
        'source',
        'related_invoice_id',
        'related_payment_id',
        'attachments',
        'internal_notes',
        'tags',
    ];

    protected $casts = [
        'priority' => TicketPriority::class,
        'status' => TicketStatus::class,
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'response_time_minutes' => 'integer',
        'resolution_time_minutes' => 'integer',
        'attachments' => 'array',
        'tags' => 'array',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function relatedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'related_invoice_id');
    }

    public function relatedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'related_payment_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [TicketStatus::OPEN, TicketStatus::IN_PROGRESS, TicketStatus::ON_HOLD]);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, TicketStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, TicketPriority $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_at', '<', now())
            ->whereIn('status', [TicketStatus::OPEN, TicketStatus::IN_PROGRESS]);
    }
}
