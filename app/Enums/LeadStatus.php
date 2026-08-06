<?php

namespace App\Enums;

enum LeadStatus: string
{
    case NEW = 'new';
    case CONTACTED = 'contacted';
    case SITE_SURVEY = 'site-survey';
    case CONVERTED = 'converted';
    case LOST = 'lost';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::NEW->value => self::NEW->label(),
            self::CONTACTED->value => self::CONTACTED->label(),
            self::SITE_SURVEY->value => self::SITE_SURVEY->label(),
            self::CONVERTED->value => self::CONVERTED->label(),
            self::LOST->value => self::LOST->label(),
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::NEW => 'New',
            self::CONTACTED => 'Contacted',
            self::SITE_SURVEY => 'Site Survey',
            self::CONVERTED => 'Converted',
            self::LOST => 'Lost',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NEW => 'info',
            self::CONTACTED => 'warning',
            self::SITE_SURVEY => 'primary',
            self::CONVERTED => 'success',
            self::LOST => 'danger',
        };
    }
}
