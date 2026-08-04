<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BKASH = 'bkash';
    case NAGAD = 'nagad';
    case SSLCOMMERZ = 'sslcommerz';
    case BANK = 'bank';
    case CASH = 'cash';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            'bKash' => self::BKASH->value,
            'Nagad' => self::NAGAD->value,
            'SSLCommerz' => self::SSLCOMMERZ->value,
            'Bank Transfer' => self::BANK->value,
            'Cash' => self::CASH->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::BKASH => 'bKash',
            self::NAGAD => 'Nagad',
            self::SSLCOMMERZ => 'SSLCommerz',
            self::BANK => 'Bank Transfer',
            self::CASH => 'Cash',
        };
    }
}
