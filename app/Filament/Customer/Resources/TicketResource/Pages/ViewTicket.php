<?php

namespace App\Filament\Customer\Resources\TicketResource\Pages;

use App\Filament\Customer\Resources\TicketResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
