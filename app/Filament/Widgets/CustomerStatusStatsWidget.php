<?php

namespace App\Filament\Widgets;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerStatusStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Active', Customer::query()->where('status', CustomerStatus::ACTIVE)->count())
                ->description('Active customers')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Suspended', Customer::query()->where('status', CustomerStatus::SUSPENDED)->count())
                ->description('Suspended customers')
                ->descriptionIcon('heroicon-o-pause-circle')
                ->color('warning'),
            Stat::make('Pending', Customer::query()->where('status', CustomerStatus::PENDING)->count())
                ->description('Pending activation')
                ->descriptionIcon('heroicon-o-clock')
                ->color('info'),
            Stat::make('Terminated', Customer::query()->where('status', CustomerStatus::TERMINATED)->count())
                ->description('Terminated customers')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger'),
        ];
    }

    protected int|string|array $columnSpan = '2';
}
