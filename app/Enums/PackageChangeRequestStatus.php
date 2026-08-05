<?php

namespace App\Enums;

enum PackageChangeRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            'Pending' => self::PENDING->value,
            'Approved' => self::APPROVED->value,
            'Rejected' => self::REJECTED->value,
            'Processing' => self::PROCESSING->value,
            'Completed' => self::COMPLETED->value,
            'Cancelled' => self::CANCELLED->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::PROCESSING => 'info',
            self::COMPLETED => 'emerald',
            self::CANCELLED => 'secondary',
        };
    }
}
