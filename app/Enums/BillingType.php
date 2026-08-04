<?php

namespace App\Enums;

enum BillingType: string
{
    case PREPAID = 'prepaid';
    case POSTPAID = 'postpaid';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            'Prepaid' => self::PREPAID->value,
            'Postpaid' => self::POSTPAID->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::PREPAID => 'Prepaid',
            self::POSTPAID => 'Postpaid',
        };
    }
}
