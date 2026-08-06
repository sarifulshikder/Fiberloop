<?php

namespace App\Enums;

enum StockTransactionType: string
{
    case RECEIPT = 'receipt';
    case ISSUE = 'issue';
    case RETURN = 'return';
    case TRANSFER = 'transfer';
    case ADJUSTMENT = 'adjustment';
    case RETIREMENT = 'retirement';
    case DISPOSAL = 'disposal';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::RECEIPT->value => self::RECEIPT->label(),
            self::ISSUE->value => self::ISSUE->label(),
            self::RETURN->value => self::RETURN->label(),
            self::TRANSFER->value => self::TRANSFER->label(),
            self::ADJUSTMENT->value => self::ADJUSTMENT->label(),
            self::RETIREMENT->value => self::RETIREMENT->label(),
            self::DISPOSAL->value => self::DISPOSAL->label(),
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::RECEIPT => 'Receipt',
            self::ISSUE => 'Issue',
            self::RETURN => 'Return',
            self::TRANSFER => 'Transfer',
            self::ADJUSTMENT => 'Adjustment',
            self::RETIREMENT => 'Retirement',
            self::DISPOSAL => 'Disposal',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::RECEIPT => 'success',
            self::ISSUE => 'primary',
            self::RETURN => 'warning',
            self::TRANSFER => 'info',
            self::ADJUSTMENT => 'secondary',
            self::RETIREMENT => 'danger',
            self::DISPOSAL => 'dark',
        };
    }

    public function isIncoming(): bool
    {
        return in_array($this, [self::RECEIPT, self::RETURN, self::ADJUSTMENT]);
    }

    public function isOutgoing(): bool
    {
        return in_array($this, [self::ISSUE, self::TRANSFER, self::RETIREMENT, self::DISPOSAL]);
    }
}
