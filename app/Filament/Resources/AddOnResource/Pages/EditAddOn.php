<?php

namespace App\Filament\Resources\AddOnResource\Pages;

use App\Filament\Resources\AddOnResource;
use Filament\Resources\Pages\EditRecord;

class EditAddOn extends EditRecord
{
    protected static string $resource = AddOnResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}