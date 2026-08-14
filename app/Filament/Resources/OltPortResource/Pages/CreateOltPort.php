<?php

namespace App\Filament\Resources\OltPortResource\Pages;

use App\Filament\Resources\OltPortResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOltPort extends CreateRecord
{
    protected static string $resource = OltPortResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
