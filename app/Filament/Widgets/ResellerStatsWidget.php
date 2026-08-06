<?php

namespace App\Filament\Widgets;

use App\Models\Reseller;
use App\Models\ResellerApprovalRequest;
use App\Models\ResellerCommissionLedger;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ResellerStatsWidget extends BaseWidget
{
    protected static ?int $sort = 20;

    protected function getStats(): array
    {
        $totalResellers = Reseller::count();
        $activeResellers = Reseller::where('status', 'active')->count();
        $pendingApprovals = ResellerApprovalRequest::pending()->count();

        // Total commission paid (sum of 'earned' entries) in poysha → convert to BDT
        $totalCommissionPoysha = ResellerCommissionLedger::where('type', 'earned')
            ->where('amount', '>', 0)
            ->sum('amount');
        $totalCommissionBdt = '৳' . number_format($totalCommissionPoysha / 100, 2);

        return [
            Stat::make('Total Resellers', $totalResellers)
                ->icon('heroicon-o-users')
                ->color('primary'),

            Stat::make('Active Resellers', $activeResellers)
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Total Commission Paid', $totalCommissionBdt)
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->description('Cumulative commissions earned'),

            Stat::make('Pending Approvals', $pendingApprovals)
                ->icon('heroicon-o-clipboard-document-check')
                ->color($pendingApprovals > 0 ? 'warning' : 'gray')
                ->description($pendingApprovals > 0 ? 'Requires admin review' : 'All clear'),
        ];
    }
}
