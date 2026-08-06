<?php

namespace App\Enums;

enum ReconciliationStatus: string
{
    case PENDING = 'pending';
    case MATCHED = 'matched';
    case DISCREPANCY = 'discrepancy';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::PENDING->value => self::PENDING->label(),
            self::MATCHED->value => self::MATCHED->label(),
            self::DISCREPANCY->value => self::DISCREPANCY->label(),
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::MATCHED => 'Matched',
            self::DISCREPANCY => 'Discrepancy',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::MATCHED => 'success',
            self::DISCREPANCY => 'danger',
        };
    }
}
