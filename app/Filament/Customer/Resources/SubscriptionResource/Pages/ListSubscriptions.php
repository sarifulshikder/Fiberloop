<?php

namespace App\Filament\Customer\Resources\SubscriptionResource\Pages;

use App\Filament\Customer\Resources\SubscriptionResource;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
