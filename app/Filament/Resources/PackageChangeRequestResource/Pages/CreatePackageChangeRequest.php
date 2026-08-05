<?php

namespace App\Filament\Resources\PackageChangeRequestResource\Pages;

use App\Filament\Resources\PackageChangeRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePackageChangeRequest extends CreateRecord
{
    protected static string $resource = PackageChangeRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        activity()
            ->by(auth()->user())
            ->on($this->record)
            ->withProperties(['action' => 'created'])
            ->log('Package change request created');
    }
}
