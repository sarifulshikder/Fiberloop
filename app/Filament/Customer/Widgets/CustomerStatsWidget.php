<?php

namespace App\Filament\Customer\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\TicketStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Ticket;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Quick Stats';

    protected function getStats(): array
    {
        $customerId = auth('customer')->id();

        $totalOutstanding = Invoice::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', [InvoiceStatus::SENT, InvoiceStatus::PARTIAL, InvoiceStatus::OVERDUE])
            ->sum('outstanding_amount');

        $totalPaid = Payment::query()
            ->whereHas('invoice', fn ($q) => $q->where('customer_id', $customerId))
            ->where('status', 'completed')
            ->sum('amount');

        $openTickets = Ticket::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', [TicketStatus::OPEN, TicketStatus::IN_PROGRESS])
            ->count();

        $totalTickets = Ticket::query()
            ->where('customer_id', $customerId)
            ->count();

        return [
            Stat::make('Outstanding Balance', 'BDT ' . number_format($totalOutstanding, 2))
                ->description('Total unpaid amount')
                ->descriptionIcon('heroicon-o-exclamation-circle')
                ->color($totalOutstanding > 0 ? 'danger' : 'success'),

            Stat::make('Total Paid', 'BDT ' . number_format($totalPaid, 2))
                ->description('All time payments')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Open Tickets', $openTickets)
                ->description('Tickets awaiting resolution')
                ->descriptionIcon('heroicon-o-chat-bubble-left-ellipsis')
                ->color($openTickets > 0 ? 'warning' : 'success'),

            Stat::make('Total Tickets', $totalTickets)
                ->description('All support tickets')
                ->descriptionIcon('heroicon-o-document-chart-bar')
                ->color('info'),
        ];
    }
}
