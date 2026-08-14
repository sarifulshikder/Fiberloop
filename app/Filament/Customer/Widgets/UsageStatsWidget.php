<?php

namespace App\Filament\Customer\Widgets;

use App\Models\RadAcct;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UsageStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationLabel = 'Usage Statistics';

    protected function getStats(): array
    {
        $customerId = auth('customer')->id();

        // Get current month usage from radacct
        $currentMonthUsage = RadAcct::query()
            ->whereHas('radiusCustomer', fn ($q) => $q->where('customer_id', $customerId))
            ->where('acctstarttime', '>=', now()->startOfMonth())
            ->sum('acctinputoctets') +
            RadAcct::query()
            ->whereHas('radiusCustomer', fn ($q) => $q->where('customer_id', $customerId))
            ->where('acctstarttime', '>=', now()->startOfMonth())
            ->sum('acctoutputoctets');

        // Convert bytes to GB
        $usageGB = $currentMonthUsage / (1024 * 1024 * 1024);

        // Get active sessions
        $activeSessions = RadAcct::query()
            ->whereHas('radiusCustomer', fn ($q) => $q->where('customer_id', $customerId))
            ->whereNull('acctstoptime')
            ->count();

        return [
            Stat::make('Current Month Usage', number_format($usageGB, 2) . ' GB')
                ->description('Data usage this month')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('info'),

            Stat::make('Active Sessions', $activeSessions)
                ->description('Currently connected devices')
                ->descriptionIcon('heroicon-o-device-phone-mobile')
                ->color($activeSessions > 0 ? 'success' : 'gray'),

            Stat::make('Last Activity', optional(RadAcct::query()
                ->whereHas('radiusCustomer', fn ($q) => $q->where('customer_id', $customerId))
                ->latest('acctstarttime')
                ->first())?->acctstarttime?->diffForHumans() ?? 'Never')
                ->description('Most recent connection')
                ->descriptionIcon('heroicon-o-clock')
                ->color('gray'),
        ];
    }
}
