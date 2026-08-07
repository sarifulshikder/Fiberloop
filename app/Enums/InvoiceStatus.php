<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case PAID = 'paid';
    case PARTIAL = 'partial';
    case OVERDUE = 'overdue';
    case VOID = 'void';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::DRAFT->value => self::DRAFT->label(),
            self::SENT->value => self::SENT->label(),
            self::PAID->value => self::PAID->label(),
            self::PARTIAL->value => self::PARTIAL->label(),
            self::OVERDUE->value => self::OVERDUE->label(),
            self::VOID->value => self::VOID->label(),
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent',
            self::PAID => 'Paid',
            self::PARTIAL => 'Partial',
            self::OVERDUE => 'Overdue',
            self::VOID => 'Void',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'secondary',
            self::SENT => 'info',
            self::PAID => 'success',
            self::PARTIAL => 'warning',
            self::OVERDUE => 'danger',
            self::VOID => 'dark',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isVoid(): bool
    {
        return $this === self::VOID;
    }

    public function isUnpaid(): bool
    {
        return !$this->isPaid();
    }
}
