<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalCustomersWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Customers', number_format(Customer::query()->count()))
                ->description('All customers in the system')
                ->descriptionIcon('heroicon-o-users')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('success'),
        ];
    }

    protected int|string|array $columnSpan = 1;
}
