<?php

namespace App\Filament\Resources\PackageChangeRequestResource\Pages;

use App\Filament\Resources\PackageChangeRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPackageChangeRequest extends EditRecord
{
    protected static string $resource = PackageChangeRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterSave(): void
    {
        activity()
            ->by(auth()->user())
            ->on($this->record)
            ->withProperties(['action' => 'updated'])
            ->log('Package change request updated');
    }
}
