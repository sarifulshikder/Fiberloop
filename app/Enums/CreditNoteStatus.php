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
            self::DRAFT->value => self::DRAFT->label(),
            self::APPROVED->value => self::APPROVED->label(),
            self::APPLIED->value => self::APPLIED->label(),
            self::CANCELLED->value => self::CANCELLED->label(),
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
