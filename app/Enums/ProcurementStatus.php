<?php

namespace App\Enums;

enum ProcurementStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case ORDERED = 'ordered';
    case PARTIALLY_RECEIVED = 'partially_received';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::DRAFT->value => self::DRAFT->label(),
            self::PENDING_APPROVAL->value => self::PENDING_APPROVAL->label(),
            self::APPROVED->value => self::APPROVED->label(),
            self::ORDERED->value => self::ORDERED->label(),
            self::PARTIALLY_RECEIVED->value => self::PARTIALLY_RECEIVED->label(),
            self::RECEIVED->value => self::RECEIVED->label(),
            self::CANCELLED->value => self::CANCELLED->label(),
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::ORDERED => 'Ordered',
            self::PARTIALLY_RECEIVED => 'Partially Received',
            self::RECEIVED => 'Received',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'secondary',
            self::PENDING_APPROVAL => 'warning',
            self::APPROVED => 'primary',
            self::ORDERED => 'info',
            self::PARTIALLY_RECEIVED => 'info',
            self::RECEIVED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING_APPROVAL, self::APPROVED, self::ORDERED, self::PARTIALLY_RECEIVED]);
    }

    public function isCompleted(): bool
    {
        return in_array($this, [self::RECEIVED, self::CANCELLED]);
    }

    public function canBeReceived(): bool
    {
        return in_array($this, [self::ORDERED, self::PARTIALLY_RECEIVED]);
    }
}
