<?php

namespace App\Filament\Resources\IpPoolResource\Pages;

use App\Filament\Resources\IpPoolResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIpPool extends EditRecord
{
    protected static string $resource = IpPoolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
