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
            self::CREDIT->value => self::CREDIT->label(),
            self::DEBIT->value => self::DEBIT->label(),
            self::REFUND->value => self::REFUND->label(),
            self::ADJUSTMENT->value => self::ADJUSTMENT->label(),
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
