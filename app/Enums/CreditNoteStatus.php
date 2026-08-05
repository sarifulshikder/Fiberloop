<?php

namespace App\Enums;

enum CreditNoteStatus: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case APPLIED = 'applied';
    case CANCELLED = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            'Draft' => self::DRAFT->value,
            'Approved' => self::APPROVED->value,
            'Applied' => self::APPLIED->value,
            'Cancelled' => self::CANCELLED->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::APPROVED => 'Approved',
            self::APPLIED => 'Applied',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'secondary',
            self::APPROVED => 'info',
            self::APPLIED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
