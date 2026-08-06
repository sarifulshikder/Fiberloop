<?php

namespace App\Enums;

enum RefundStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PROCESSED = 'processed';
    case REJECTED = 'rejected';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::PENDING->value => self::PENDING->label(),
            self::APPROVED->value => self::APPROVED->label(),
            self::PROCESSED->value => self::PROCESSED->label(),
            self::REJECTED->value => self::REJECTED->label(),
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::PROCESSED => 'Processed',
            self::REJECTED => 'Rejected',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'info',
            self::PROCESSED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
