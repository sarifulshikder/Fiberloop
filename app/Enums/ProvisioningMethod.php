<?php

namespace App\Enums;

enum ProvisioningMethod: string
{
    case RADIUS = 'radius';
    case API = 'api';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::RADIUS->value => self::RADIUS->label(),
            self::API->value => self::API->label(),
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::RADIUS => 'RADIUS (FreeRADIUS)',
            self::API => 'MikroTik API (local PPP secret)',
        };
    }
}
