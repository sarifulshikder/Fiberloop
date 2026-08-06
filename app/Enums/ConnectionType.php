<?php

namespace App\Enums;

enum ConnectionType: string
{
    case PPPOE = 'pppoe';
    case HOTSPOT = 'hotspot';
    case STATIC = 'static';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::PPPOE->value => self::PPPOE->label(),
            self::HOTSPOT->value => self::HOTSPOT->label(),
            self::STATIC->value => self::STATIC->label(),
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::PPPOE => 'PPPoE',
            self::HOTSPOT => 'Hotspot',
            self::STATIC => 'Static IP',
        };
    }
}
