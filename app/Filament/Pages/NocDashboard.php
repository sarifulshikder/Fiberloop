<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class NocDashboard extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-server';
    protected static UnitEnum|string|null $navigationGroup = 'Network';
    protected static ?string $title = 'NOC Dashboard';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.noc-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'noc_engineer']);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\NocDeviceHealthWidget::class,
            \App\Filament\Widgets\NocOutagesWidget::class,
        ];
    }
}
