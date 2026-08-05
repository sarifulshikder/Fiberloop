<?php

namespace App\Filament\Resources\CustomerNoteResource\Pages;

use App\Filament\Resources\CustomerNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerNote extends CreateRecord
{
    protected static string $resource = CustomerNoteResource::class;

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
            ->log('Customer note created');
    }
}
