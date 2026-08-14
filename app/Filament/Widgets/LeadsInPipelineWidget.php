<?php

namespace App\Filament\Widgets;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LeadsInPipelineWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('New Leads', number_format(Lead::query()->where('status', LeadStatus::NEW)->count()))
                ->description('New leads awaiting contact')
                ->descriptionIcon('heroicon-o-sparkles')
                ->color('info'),
            Stat::make('Contacted', number_format(Lead::query()->where('status', LeadStatus::CONTACTED)->count()))
                ->description('Leads contacted')
                ->descriptionIcon('heroicon-o-phone')
                ->color('primary'),
            Stat::make('Site Survey', number_format(Lead::query()->where('status', LeadStatus::SITE_SURVEY)->count()))
                ->description('Leads in site survey')
                ->descriptionIcon('heroicon-o-map-pin')
                ->color('warning'),
            Stat::make('Converted', number_format(Lead::query()->where('status', LeadStatus::CONVERTED)->count()))
                ->description('Leads converted to customers')
                ->descriptionIcon('heroicon-o-check-badge')
                ->color('success'),
        ];
    }

    protected int|string|array $columnSpan = 1;
}
