<?php

namespace App\Filament\Widgets;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AdminDashboardStats extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('admin');
    }

    protected function getStats(): array
    {
        $stats = Cache::remember('admin_dashboard_stats', 300, function () {
            $startOfMonth = now()->startOfMonth();
            $startOfDay = now()->startOfDay();

            $revenue = Invoice::where('status', InvoiceStatus::PAID)
                ->where('created_at', '>=', $startOfMonth)
                ->sum('total') / 100;

            $outstanding = Invoice::whereIn('status', [InvoiceStatus::SENT, InvoiceStatus::OVERDUE, InvoiceStatus::PARTIAL, InvoiceStatus::DRAFT])
                ->sum('total') / 100;

            $activeCount = Customer::where('status', CustomerStatus::ACTIVE)->count();
            $suspendedCount = Customer::where('status', CustomerStatus::SUSPENDED)->count();
            $churnedCount = Customer::where('status', CustomerStatus::TERMINATED)->count();
            $pendingCount = Customer::where('status', CustomerStatus::PENDING)->count();

            $dailyCollections = Invoice::where('status', InvoiceStatus::PAID)
                ->where('created_at', '>=', $startOfDay)
                ->sum('total') / 100;

            return [
                'revenue' => $revenue,
                'outstanding' => $outstanding,
                'active' => $activeCount,
                'suspended' => $suspendedCount,
                'churned' => $churnedCount,
                'pending' => $pendingCount,
                'daily_collections' => $dailyCollections,
            ];
        });

        return [
            Stat::make('Monthly Revenue', 'BDT ' . number_format($stats['revenue'], 2))
                ->description('Revenue this month')
                ->descriptionIcon('heroicon-o-currency-bangladeshi')
                ->color('success'),
            Stat::make('Outstanding Dues', 'BDT ' . number_format($stats['outstanding'], 2))
                ->description('Total unpaid invoices')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('warning'),
            Stat::make('Daily Collections', 'BDT ' . number_format($stats['daily_collections'], 2))
                ->description('Collected today')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('info'),
            Stat::make('Active Customers', number_format($stats['active']))
                ->description('Currently active')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),
        ];
    }

    protected int|string|array $columnSpan = 2;
}
