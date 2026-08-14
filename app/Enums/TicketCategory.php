<?php

namespace App\Enums;

enum TicketCategory: string
{
    case TECHNICAL = 'Technical';
    case BILLING = 'Billing';
    case SALES = 'Sales';
    case SUPPORT = 'Support';
    case COMPLAINT = 'Complaint';
    case INSTALLATION = 'Installation';
    case MAINTENANCE = 'Maintenance';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->name])
            ->toArray();
    }
}
