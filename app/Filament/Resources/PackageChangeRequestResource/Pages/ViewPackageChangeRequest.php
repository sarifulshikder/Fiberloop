<?php

namespace App\Filament\Resources\PackageChangeRequestResource\Pages;

use App\Filament\Resources\PackageChangeRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPackageChangeRequest extends ViewRecord
{
    protected static string $resource = PackageChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
