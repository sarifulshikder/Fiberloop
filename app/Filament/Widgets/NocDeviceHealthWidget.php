<?php

namespace App\Filament\Widgets;

use App\Models\NetworkDevice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NocDeviceHealthWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $total = NetworkDevice::count();
        $up = NetworkDevice::where('is_reachable', true)->count();
        $down = NetworkDevice::where('is_reachable', false)->count();

        return [
            Stat::make('Total Devices', $total)
                ->icon('heroicon-o-server-stack'),
            Stat::make('Devices Online', $up)
                ->color('success')
                ->icon('heroicon-o-check-circle'),
            Stat::make('Devices Offline', $down)
                ->color($down > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-x-circle'),
        ];
    }
}
