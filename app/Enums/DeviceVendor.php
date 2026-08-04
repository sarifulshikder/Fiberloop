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
            'MikroTik' => self::MIKROTIK->value,
            'Huawei' => self::HUAWEI->value,
            'ZTE' => self::ZTE->value,
            'Nokia' => self::NOKIA->value,
            'Cisco' => self::CISCO->value,
            'Other' => self::OTHER->value,
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
