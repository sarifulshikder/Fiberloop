<?php

namespace App\Filament\Resources\AddOnResource\Pages;

use App\Filament\Resources\AddOnResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAddOn extends CreateRecord
{
    protected static string $resource = AddOnResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
