<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case SUSPENDED = 'suspended';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            'Active' => self::ACTIVE->value,
            'Expired' => self::EXPIRED->value,
            'Cancelled' => self::CANCELLED->value,
            'Suspended' => self::SUSPENDED->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Active',
            self::EXPIRED => 'Expired',
            self::CANCELLED => 'Cancelled',
            self::SUSPENDED => 'Suspended',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::ACTIVE => 'success',
            self::EXPIRED => 'warning',
            self::CANCELLED => 'secondary',
            self::SUSPENDED => 'danger',
        };
    }
}
