<?php

namespace App\Enums;

enum NetworkManagementProtocol: string
{
    case SNMP = 'snmp';
    case SSH = 'ssh';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::SNMP->value => self::SNMP->label(),
            self::SSH->value => self::SSH->label(),
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::SNMP => 'SNMP',
            self::SSH => 'SSH CLI',
        };
    }
}
