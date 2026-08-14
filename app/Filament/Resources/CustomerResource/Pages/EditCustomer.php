<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Concerns\ManagesSubscriberProvisioning;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    use ManagesSubscriberProvisioning;

    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterSave(): void
    {
        // Log customer update
        activity()
            ->by(auth()->user())
            ->on($this->record)
            ->withProperties(['action' => 'updated', 'changes' => $this->record->getChanges()])
            ->log('Customer updated');

        // Create/update the subscription from the selected package and
        // provision the subscriber through their chosen method.
        $this->syncSubscriptionAndProvision();
    }
}
