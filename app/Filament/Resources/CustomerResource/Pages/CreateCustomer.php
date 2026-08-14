<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Concerns\ManagesSubscriberProvisioning;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    use ManagesSubscriberProvisioning;

    protected static string $resource = CustomerResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }



    protected function afterCreate(): void
    {
        // Log customer creation
        activity()
            ->by(auth()->user())
            ->on($this->record)
            ->withProperties(['action' => 'created'])
            ->log('Customer created');

        // Create/update the subscription from the selected package and
        // provision the subscriber through their chosen method.
        $this->syncSubscriptionAndProvision();
    }
}
