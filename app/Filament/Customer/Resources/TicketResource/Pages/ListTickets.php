<?php

namespace App\Filament\Customer\Resources\TicketResource\Pages;

use App\Filament\Customer\Resources\TicketResource;
use Filament\Resources\Pages\ListRecords;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Customers can create tickets
        ];
    }
}
