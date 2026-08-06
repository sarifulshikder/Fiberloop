<?php

namespace App\Enums;

enum DeviceVendor: string
{
    case MIKROTIK = 'mikrotik';
    case HUAWEI = 'huawei';
    case ZTE = 'zte';
    case NOKIA = 'nokia';
    case CISCO = 'cisco';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::MIKROTIK->value => self::MIKROTIK->label(),
            self::HUAWEI->value => self::HUAWEI->label(),
            self::ZTE->value => self::ZTE->label(),
            self::NOKIA->value => self::NOKIA->label(),
            self::CISCO->value => self::CISCO->label(),
            self::OTHER->value => self::OTHER->label(),
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::MIKROTIK => 'MikroTik',
            self::HUAWEI => 'Huawei',
            self::ZTE => 'ZTE',
            self::NOKIA => 'Nokia',
            self::CISCO => 'Cisco',
            self::OTHER => 'Other',
        };
    }
}
