<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case CREDIT = 'credit';
    case DEBIT = 'debit';
    case REFUND = 'refund';
    case ADJUSTMENT = 'adjustment';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            'Credit' => self::CREDIT->value,
            'Debit' => self::DEBIT->value,
            'Refund' => self::REFUND->value,
            'Adjustment' => self::ADJUSTMENT->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::CREDIT => 'Credit',
            self::DEBIT => 'Debit',
            self::REFUND => 'Refund',
            self::ADJUSTMENT => 'Adjustment',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::CREDIT => 'success',
            self::DEBIT => 'danger',
            self::REFUND => 'info',
            self::ADJUSTMENT => 'warning',
        };
    }

    public function sign(): int
    {
        return match($this) {
            self::CREDIT => 1,
            self::REFUND => 1,
            self::DEBIT => -1,
            self::ADJUSTMENT => 0, // Can be positive or negative
        };
    }
}
