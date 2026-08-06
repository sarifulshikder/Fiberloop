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
            self::IN_STOCK->value => self::IN_STOCK->label(),
            self::ASSIGNED->value => self::ASSIGNED->label(),
            self::FAULTY->value => self::FAULTY->label(),
            self::RETIRED->value => self::RETIRED->label(),
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
