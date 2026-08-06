<?php

namespace App\Enums;

enum PackageBillingCycle: string
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case BIANNUAL = 'biannual';
    case ANNUAL = 'annual';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::MONTHLY->value => self::MONTHLY->label(),
            self::QUARTERLY->value => self::QUARTERLY->label(),
            self::BIANNUAL->value => self::BIANNUAL->label(),
            self::ANNUAL->value => self::ANNUAL->label(),
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::BIANNUAL => 'Bi-Annual',
            self::ANNUAL => 'Annual',
        };
    }

    public function days(): int
    {
        return match($this) {
            self::MONTHLY => 30,
            self::QUARTERLY => 90,
            self::BIANNUAL => 180,
            self::ANNUAL => 365,
        };
    }
}
