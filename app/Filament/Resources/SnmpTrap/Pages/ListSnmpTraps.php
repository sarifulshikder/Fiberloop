<?php

namespace App\Filament\Resources\SnmpTrap\Pages;

use App\Filament\Resources\SnmpTrapResource;
use Filament\Resources\Pages\ListRecords;

class ListSnmpTraps extends ListRecords
{
    protected static string $resource = SnmpTrapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
