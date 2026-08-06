<?php

namespace App\Enums;

enum ProcurementItemStatus: string
{
    case PENDING = 'pending';
    case ORDERED = 'ordered';
    case PARTIALLY_RECEIVED = 'partially_received';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';
    case RETURNED = 'returned';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::PENDING->value => self::PENDING->label(),
            self::ORDERED->value => self::ORDERED->label(),
            self::PARTIALLY_RECEIVED->value => self::PARTIALLY_RECEIVED->label(),
            self::RECEIVED->value => self::RECEIVED->label(),
            self::CANCELLED->value => self::CANCELLED->label(),
            self::RETURNED->value => self::RETURNED->label(),
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::ORDERED => 'Ordered',
            self::PARTIALLY_RECEIVED => 'Partially Received',
            self::RECEIVED => 'Received',
            self::CANCELLED => 'Cancelled',
            self::RETURNED => 'Returned',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'secondary',
            self::ORDERED => 'info',
            self::PARTIALLY_RECEIVED => 'info',
            self::RECEIVED => 'success',
            self::CANCELLED => 'danger',
            self::RETURNED => 'warning',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isReceived(): bool
    {
        return $this === self::RECEIVED;
    }

    public function isPartiallyReceived(): bool
    {
        return $this === self::PARTIALLY_RECEIVED;
    }

    public function canBeReceived(): bool
    {
        return in_array($this, [self::PENDING, self::ORDERED, self::PARTIALLY_RECEIVED]);
    }
}
