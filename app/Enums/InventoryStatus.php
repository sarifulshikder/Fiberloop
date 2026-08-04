<?php

namespace App\Enums;

enum InventoryStatus: string
{
    case IN_STOCK = 'in_stock';
    case ASSIGNED = 'assigned';
    case FAULTY = 'faulty';
    case RETIRED = 'retired';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            'In Stock' => self::IN_STOCK->value,
            'Assigned' => self::ASSIGNED->value,
            'Faulty' => self::FAULTY->value,
            'Retired' => self::RETIRED->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::IN_STOCK => 'In Stock',
            self::ASSIGNED => 'Assigned',
            self::FAULTY => 'Faulty',
            self::RETIRED => 'Retired',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::IN_STOCK => 'success',
            self::ASSIGNED => 'primary',
            self::FAULTY => 'danger',
            self::RETIRED => 'secondary',
        };
    }
}
