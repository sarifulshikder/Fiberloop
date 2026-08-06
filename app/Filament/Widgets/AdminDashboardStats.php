<?php

namespace App\Filament\Widgets;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AdminDashboardStats extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin');
    }

    protected function getStats(): array
    {
        $stats = Cache::remember('admin_dashboard_stats', 300, function () {
            $startOfMonth = now()->startOfMonth();

            $revenue = Invoice::where('status', 'paid')
                ->where('created_at', '>=', $startOfMonth)
                ->sum('total') / 100;

            $outstanding = Invoice::where('status', 'unpaid')
                ->sum('total') / 100;

            $activeCount = Customer::where('status', CustomerStatus::ACTIVE)->count();
            $suspendedCount = Customer::where('status', CustomerStatus::SUSPENDED)->count();
            $churnedCount = Customer::where('status', CustomerStatus::TERMINATED)->count();

            return [
                'revenue' => $revenue,
                'outstanding' => $outstanding,
                'active' => $activeCount,
                'suspended' => $suspendedCount,
                'churned' => $churnedCount,
            ];
        });

        return [
            Stat::make('Monthly Revenue', number_format($stats['revenue'], 2) . ' BDT'),
            Stat::make('Outstanding Dues', number_format($stats['outstanding'], 2) . ' BDT'),
            Stat::make('Active Customers', number_format($stats['active'])),
            Stat::make('Suspended/Churned', number_format($stats['suspended']) . ' / ' . number_format($stats['churned'])),
        ];
    }
}
