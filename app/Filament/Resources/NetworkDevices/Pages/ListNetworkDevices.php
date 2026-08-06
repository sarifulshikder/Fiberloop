<?php

namespace App\Filament\Resources\NetworkDevices\Pages;

use App\Filament\Resources\NetworkDevices\NetworkDeviceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNetworkDevices extends ListRecords
{
    protected static string $resource = NetworkDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
