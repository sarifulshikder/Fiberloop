<?php

namespace App\Filament\Customer\Resources\SubscriptionResource\Pages;

use App\Filament\Customer\Resources\SubscriptionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
