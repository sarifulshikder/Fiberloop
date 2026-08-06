<?php

namespace App\Filament\Resources\PackageZoneResource\Pages;

use App\Filament\Resources\PackageZoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePackageZone extends CreateRecord
{
    protected static string $resource = PackageZoneResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
