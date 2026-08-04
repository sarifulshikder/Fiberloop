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
            'Draft' => self::DRAFT->value,
            'Sent' => self::SENT->value,
            'Paid' => self::PAID->value,
            'Partial' => self::PARTIAL->value,
            'Overdue' => self::OVERDUE->value,
            'Void' => self::VOID->value,
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
}
