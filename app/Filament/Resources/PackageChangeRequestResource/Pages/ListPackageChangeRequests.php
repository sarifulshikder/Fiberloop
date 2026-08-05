<?php

namespace App\Filament\Resources\PackageChangeRequestResource\Pages;

use App\Filament\Resources\PackageChangeRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPackageChangeRequests extends ListRecords
{
    protected static string $resource = PackageChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
