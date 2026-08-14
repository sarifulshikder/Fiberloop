<?php

namespace App\Filament\Resources\SnmpCommunity\Pages;

use App\Filament\Resources\SnmpCommunityResource;
use Filament\Resources\Pages\ListRecords;

class ListSnmpCommunities extends ListRecords
{
    protected static string $resource = SnmpCommunityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
