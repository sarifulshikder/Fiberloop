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
            self::GENERAL->value => self::GENERAL->label(),
            self::CALL->value => self::CALL->label(),
            self::COMPLAINT->value => self::COMPLAINT->label(),
            self::TECHNICIAN_VISIT->value => self::TECHNICIAN_VISIT->label(),
            self::PAYMENT->value => self::PAYMENT->label(),
            self::SUPPORT->value => self::SUPPORT->label(),
            self::SALES->value => self::SALES->label(),
            self::INSTALLATION->value => self::INSTALLATION->label(),
            self::MAINTENANCE->value => self::MAINTENANCE->label(),
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
