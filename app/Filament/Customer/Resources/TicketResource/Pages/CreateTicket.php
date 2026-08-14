<?php

namespace App\Filament\Customer\Resources\TicketResource\Pages;

use App\Filament\Customer\Resources\TicketResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        // Send notification to support team
        // This will be handled by event listeners
    }
}
