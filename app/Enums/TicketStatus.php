<?php

namespace App\Enums;

enum TicketStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case ON_HOLD = 'on_hold';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::OPEN->value => self::OPEN->label(),
            self::IN_PROGRESS->value => self::IN_PROGRESS->label(),
            self::ON_HOLD->value => self::ON_HOLD->label(),
            self::RESOLVED->value => self::RESOLVED->label(),
            self::CLOSED->value => self::CLOSED->label(),
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In Progress',
            self::ON_HOLD => 'On Hold',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::OPEN => 'info',
            self::IN_PROGRESS => 'primary',
            self::ON_HOLD => 'warning',
            self::RESOLVED => 'success',
            self::CLOSED => 'secondary',
        };
    }
}
