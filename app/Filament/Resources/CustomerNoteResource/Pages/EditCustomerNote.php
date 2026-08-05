<?php

namespace App\Filament\Resources\CustomerNoteResource\Pages;

use App\Filament\Resources\CustomerNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerNote extends EditRecord
{
    protected static string $resource = CustomerNoteResource::class;

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
            ->log('Customer note updated');
    }
}
