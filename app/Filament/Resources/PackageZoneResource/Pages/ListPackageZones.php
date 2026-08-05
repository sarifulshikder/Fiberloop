<?php

namespace App\Filament\Resources\PackageZoneResource\Pages;

use App\Filament\Resources\PackageZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPackageZones extends ListRecords
{
    protected static string $resource = PackageZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}