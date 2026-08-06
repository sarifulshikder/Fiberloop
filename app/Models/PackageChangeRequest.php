<?php

namespace App\Models;

use App\Enums\PackageChangeRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackageChangeRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'customer_id',
        'subscription_id',
        'current_package_id',
        'requested_package_id',
        'created_by',
        'updated_by',
        'approved_by',
        'rejected_by',
        'type',
        'status',
        'reason',
        'effective_date',
        'proration_amount',
        'is_prorated',
        'approval_notes',
        'rejection_reason',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'type' => 'string',
        'status' => PackageChangeRequestStatus::class,
        'effective_date' => 'date',
        'proration_amount' => 'integer',
        'is_prorated' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function currentPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'current_package_id');
    }

    public function requestedPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'requested_package_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    // Scopes
    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, PackageChangeRequestStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeBySubscription($query, $subscriptionId)
    {
        return $query->where('subscription_id', $subscriptionId);
    }

    public function scopePending($query)
    {
        return $query->where('status', PackageChangeRequestStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', PackageChangeRequestStatus::APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', PackageChangeRequestStatus::REJECTED);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', PackageChangeRequestStatus::PROCESSING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', PackageChangeRequestStatus::COMPLETED);
    }

    // Status transition methods
    public function approve(User $approver, string $notes = null, $effectiveDate = null): void
    {
        $this->forceFill([
            'status' => PackageChangeRequestStatus::APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_notes' => $notes,
            'effective_date' => $effectiveDate,
        ])->save();

        activity()
            ->by($approver)
            ->on($this)
            ->withProperties([
                'old_status' => $this->getOriginal('status'),
                'new_status' => PackageChangeRequestStatus::APPROVED->value,
                'notes' => $notes,
                'effective_date' => $effectiveDate,
            ])
            ->log('Package change request approved');
    }

    public function reject(User $rejecter, string $reason = null): void
    {
        $this->forceFill([
            'status' => PackageChangeRequestStatus::REJECTED,
            'rejected_by' => $rejecter->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        activity()
            ->by($rejecter)
            ->on($this)
            ->withProperties([
                'old_status' => $this->getOriginal('status'),
                'new_status' => PackageChangeRequestStatus::REJECTED->value,
                'reason' => $reason,
            ])
            ->log('Package change request rejected');
    }

    public function markAsProcessing(User $processor): void
    {
        $this->forceFill([
            'status' => PackageChangeRequestStatus::PROCESSING,
            'updated_by' => $processor->id,
        ])->save();

        activity()
            ->by($processor)
            ->on($this)
            ->withProperties(['old_status' => $this->getOriginal('status')])
            ->log('Package change request processing started');
    }

    public function markAsCompleted(): void
    {
        $this->forceFill([
            'status' => PackageChangeRequestStatus::COMPLETED,
        ])->save();

        activity()
            ->by($this->updatedBy ?? $this->createdBy)
            ->on($this)
            ->withProperties(['old_status' => $this->getOriginal('status')])
            ->log('Package change request completed');
    }

    public function cancel(User $canceller, string $reason = null): void
    {
        $this->forceFill([
            'status' => PackageChangeRequestStatus::CANCELLED,
            'updated_by' => $canceller->id,
            'rejection_reason' => $reason,
        ])->save();

        activity()
            ->by($canceller)
            ->on($this)
            ->withProperties([
                'old_status' => $this->getOriginal('status'),
                'reason' => $reason,
            ])
            ->log('Package change request cancelled');
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === PackageChangeRequestStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === PackageChangeRequestStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === PackageChangeRequestStatus::REJECTED;
    }

    public function isProcessing(): bool
    {
        return $this->status === PackageChangeRequestStatus::PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === PackageChangeRequestStatus::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === PackageChangeRequestStatus::CANCELLED;
    }

    public function isUpgrade(): bool
    {
        return $this->type === 'upgrade';
    }

    public function isDowngrade(): bool
    {
        return $this->type === 'downgrade';
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'upgrade' => 'Upgrade',
            'downgrade' => 'Downgrade',
            'change' => 'Package Change',
            default => $this->type,
        };
    }

    /**
     * Calculate proration amount between current and requested package
     * This is a placeholder - actual logic will be implemented in Phase 5
     */
    public function calculateProration(): int
    {
        if ($this->currentPackage && $this->requestedPackage) {
            $priceDifference = $this->requestedPackage->price - $this->currentPackage->price;
            // Simple calculation - actual proration logic in Phase 5
            return $priceDifference;
        }

        return 0;
    }
}
