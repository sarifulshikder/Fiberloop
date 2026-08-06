<?php

namespace App\Filament\Widgets;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\ResellerCommissionLedger;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class ResellerDashboardStats extends BaseWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()->hasRole('reseller');
    }

    protected function getStats(): array
    {
        $userId = auth()->id();

        $stats = Cache::remember("reseller_dashboard_stats_{$userId}", 300, function () use ($userId) {
            // Relies on Phase 9 ResellerScope on Customer model which restricts to this reseller's hierarchy
            $activeCustomers = Customer::where('status', CustomerStatus::ACTIVE)->count();

            $totalCommission = ResellerCommissionLedger::where('reseller_id', auth()->user()->reseller?->id ?? 0)
                ->where('type', 'credit')
                ->sum('amount') / 100;

            $walletBalance = auth()->user()->reseller?->wallet_balance / 100 ?? 0;

            return [
                'customers' => $activeCustomers,
                'commission' => $totalCommission,
                'wallet' => $walletBalance,
            ];
        });

        return [
            Stat::make('Active Customers', number_format($stats['customers'])),
            Stat::make('Total Commission Earned', number_format($stats['commission'], 2) . ' BDT'),
            Stat::make('Wallet Balance', number_format($stats['wallet'], 2) . ' BDT')
                ->color($stats['wallet'] < 500 ? 'danger' : 'success'),
        ];
    }
}
