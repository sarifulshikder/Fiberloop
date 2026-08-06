<?php

namespace App\Enums;

enum StockTransactionReason: string
{
    case PURCHASE = 'purchase';
    case NEW_INSTALLATION = 'new_installation';
    case REPLACEMENT = 'replacement';
    case UPGRADE = 'upgrade';
    case DOWNGRADE = 'downgrade';
    case MAINTENANCE = 'maintenance';
    case FAULTY = 'faulty';
    case END_OF_LIFE = 'end_of_life';
    case CUSTOMER_TERMINATION = 'customer_termination';
    case TECHNICIAN_CHECKOUT = 'technician_checkout';
    case TECHNICIAN_CHECKIN = 'technician_checkin';
    case STOCK_ADJUSTMENT = 'stock_adjustment';
    case LOSS = 'loss';
    case THEFT = 'theft';
    case DAMAGE = 'damage';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::PURCHASE->value => self::PURCHASE->label(),
            self::NEW_INSTALLATION->value => self::NEW_INSTALLATION->label(),
            self::REPLACEMENT->value => self::REPLACEMENT->label(),
            self::UPGRADE->value => self::UPGRADE->label(),
            self::DOWNGRADE->value => self::DOWNGRADE->label(),
            self::MAINTENANCE->value => self::MAINTENANCE->label(),
            self::FAULTY->value => self::FAULTY->label(),
            self::END_OF_LIFE->value => self::END_OF_LIFE->label(),
            self::CUSTOMER_TERMINATION->value => self::CUSTOMER_TERMINATION->label(),
            self::TECHNICIAN_CHECKOUT->value => self::TECHNICIAN_CHECKOUT->label(),
            self::TECHNICIAN_CHECKIN->value => self::TECHNICIAN_CHECKIN->label(),
            self::STOCK_ADJUSTMENT->value => self::STOCK_ADJUSTMENT->label(),
            self::LOSS->value => self::LOSS->label(),
            self::THEFT->value => self::THEFT->label(),
            self::DAMAGE->value => self::DAMAGE->label(),
            self::OTHER->value => self::OTHER->label(),
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::PURCHASE => 'Purchase',
            self::NEW_INSTALLATION => 'New Installation',
            self::REPLACEMENT => 'Replacement',
            self::UPGRADE => 'Upgrade',
            self::DOWNGRADE => 'Downgrade',
            self::MAINTENANCE => 'Maintenance',
            self::FAULTY => 'Faulty',
            self::END_OF_LIFE => 'End of Life',
            self::CUSTOMER_TERMINATION => 'Customer Termination',
            self::TECHNICIAN_CHECKOUT => 'Technician Checkout',
            self::TECHNICIAN_CHECKIN => 'Technician Check-in',
            self::STOCK_ADJUSTMENT => 'Stock Adjustment',
            self::LOSS => 'Loss',
            self::THEFT => 'Theft',
            self::DAMAGE => 'Damage',
            self::OTHER => 'Other',
        };
    }

    public function category(): string
    {
        return match($this) {
            self::PURCHASE => 'incoming',
            self::NEW_INSTALLATION => 'outgoing',
            self::REPLACEMENT => 'outgoing',
            self::UPGRADE => 'outgoing',
            self::DOWNGRADE => 'incoming',
            self::MAINTENANCE => 'outgoing',
            self::FAULTY => 'incoming',
            self::END_OF_LIFE => 'outgoing',
            self::CUSTOMER_TERMINATION => 'incoming',
            self::TECHNICIAN_CHECKOUT => 'outgoing',
            self::TECHNICIAN_CHECKIN => 'incoming',
            self::STOCK_ADJUSTMENT => 'adjustment',
            self::LOSS => 'outgoing',
            self::THEFT => 'outgoing',
            self::DAMAGE => 'incoming',
            self::OTHER => 'adjustment',
        };
    }
}
