<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\LeadResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLead extends CreateRecord
{
    protected static string $resource = LeadResource::class;

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
        // Log lead creation
        activity()
            ->by(auth()->user())
            ->on($this->record)
            ->withProperties(['action' => 'created'])
            ->log('Lead created');
    }
}
