<?php

namespace App\Filament\Widgets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class SupportDashboardStats extends BaseWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('support_agent');
    }

    protected function getStats(): array
    {
        $stats = Cache::remember('support_dashboard_stats', 300, function () {
            $openTickets = Ticket::whereIn('status', [TicketStatus::OPEN, TicketStatus::IN_PROGRESS])->count();
            $breachedTickets = Ticket::whereIn('status', [TicketStatus::OPEN, TicketStatus::IN_PROGRESS])
                                    ->where('due_at', '<', now())
                                    ->count();

            return [
                'open' => $openTickets,
                'breached' => $breachedTickets,
            ];
        });

        return [
            Stat::make('Open Tickets', number_format($stats['open'])),
            Stat::make('SLA Breached Tickets', number_format($stats['breached']))
                ->color($stats['breached'] > 0 ? 'danger' : 'success'),
        ];
    }
}
