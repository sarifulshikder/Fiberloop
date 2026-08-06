<?php

namespace App\Filament\Resources\NetworkDevices\Pages;

use App\Filament\Resources\NetworkDevices\NetworkDeviceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewNetworkDevice extends ViewRecord
{
    protected static string $resource = NetworkDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
