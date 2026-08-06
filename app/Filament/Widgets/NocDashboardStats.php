<?php

namespace App\Filament\Widgets;

use App\Models\Incident;
use App\Models\NetworkDevice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class NocDashboardStats extends BaseWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('noc_engineer');
    }

    protected function getStats(): array
    {
        $stats = Cache::remember('noc_dashboard_stats', 300, function () {
            $offlineDevices = NetworkDevice::where('status', 'offline')->count();
            $onlineDevices = NetworkDevice::where('status', 'online')->count();
            $activeIncidents = Incident::where('status', 'open')->orWhere('status', 'in_progress')->count();

            return [
                'offline' => $offlineDevices,
                'online' => $onlineDevices,
                'incidents' => $activeIncidents,
            ];
        });

        return [
            Stat::make('Online Devices', number_format($stats['online']))
                ->color('success'),
            Stat::make('Offline Devices', number_format($stats['offline']))
                ->color($stats['offline'] > 0 ? 'danger' : 'success'),
            Stat::make('Active Outages', number_format($stats['incidents']))
                ->color($stats['incidents'] > 0 ? 'danger' : 'success'),
        ];
    }
}
