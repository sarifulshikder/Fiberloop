<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CustomerStatusStatsWidget;
use App\Filament\Widgets\LeadsInPipelineWidget;
use App\Filament\Widgets\TotalCustomersWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?int $navigationSort = -2;

    public function getTitle(): string
    {
        return 'Dashboard';
    }

    public function getWidgets(): array
    {
        return [
            TotalCustomersWidget::class,
            CustomerStatusStatsWidget::class,
            LeadsInPipelineWidget::class,
        ];
    }

    public function getColumns(): array|int
    {
        return 3;
    }
}
