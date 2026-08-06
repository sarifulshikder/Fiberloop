<?php

namespace App\Filament\Resources\NetworkDevices\Pages;

use App\Filament\Resources\NetworkDevices\NetworkDeviceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditNetworkDevice extends EditRecord
{
    protected static string $resource = NetworkDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
