<?php

namespace App\Filament\Customer\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class CustomerDashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?int $navigationSort = -1;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Customer\Widgets\CustomerStatsWidget::class,
            \App\Filament\Customer\Widgets\UsageStatsWidget::class,
        ];
    }

    public function getColumns(): array|int
    {
        return 2;
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Customer\Widgets\RecentInvoicesWidget::class,
        ];
    }
}
