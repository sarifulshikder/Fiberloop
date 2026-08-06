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
            self::BKASH->value => self::BKASH->label(),
            self::NAGAD->value => self::NAGAD->label(),
            self::SSLCOMMERZ->value => self::SSLCOMMERZ->label(),
            self::BANK->value => self::BANK->label(),
            self::CASH->value => self::CASH->label(),
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
