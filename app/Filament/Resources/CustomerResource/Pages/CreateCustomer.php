<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CancelAction::make(),
        ];
    }

    protected function afterCreate(): void
    {
        // Log customer creation
        activity()
            ->by(auth()->user())
            ->on($this->record)
            ->withProperties(['action' => 'created'])
            ->log('Customer created');
    }
}
