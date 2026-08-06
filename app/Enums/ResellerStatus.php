<?php

namespace App\Enums;

enum ResellerStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case TERMINATED = 'terminated';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::ACTIVE->value => self::ACTIVE->label(),
            self::SUSPENDED->value => self::SUSPENDED->label(),
            self::TERMINATED->value => self::TERMINATED->label(),
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::TERMINATED => 'Terminated',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::ACTIVE => 'success',
            self::SUSPENDED => 'warning',
            self::TERMINATED => 'danger',
        };
    }
}
