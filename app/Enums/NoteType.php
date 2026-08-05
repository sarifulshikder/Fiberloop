<?php

namespace App\Enums;

enum NoteType: string
{
    case GENERAL = 'general';
    case CALL = 'call';
    case COMPLAINT = 'complaint';
    case TECHNICIAN_VISIT = 'technician_visit';
    case PAYMENT = 'payment';
    case SUPPORT = 'support';
    case SALES = 'sales';
    case INSTALLATION = 'installation';
    case MAINTENANCE = 'maintenance';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            'General' => self::GENERAL->value,
            'Call' => self::CALL->value,
            'Complaint' => self::COMPLAINT->value,
            'Technician Visit' => self::TECHNICIAN_VISIT->value,
            'Payment' => self::PAYMENT->value,
            'Support' => self::SUPPORT->value,
            'Sales' => self::SALES->value,
            'Installation' => self::INSTALLATION->value,
            'Maintenance' => self::MAINTENANCE->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::GENERAL => 'General',
            self::CALL => 'Call',
            self::COMPLAINT => 'Complaint',
            self::TECHNICIAN_VISIT => 'Technician Visit',
            self::PAYMENT => 'Payment',
            self::SUPPORT => 'Support',
            self::SALES => 'Sales',
            self::INSTALLATION => 'Installation',
            self::MAINTENANCE => 'Maintenance',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::GENERAL => 'neutral',
            self::CALL => 'blue',
            self::COMPLAINT => 'danger',
            self::TECHNICIAN_VISIT => 'primary',
            self::PAYMENT => 'warning',
            self::SUPPORT => 'info',
            self::SALES => 'success',
            self::INSTALLATION => 'emerald',
            self::MAINTENANCE => 'amber',
        };
    }
}
