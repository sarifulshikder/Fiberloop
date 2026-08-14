<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Concerns\ManagesSubscriberProvisioning;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    use ManagesSubscriberProvisioning;

    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('provisionNow')
                ->label('Provision Now')
                ->icon('heroicon-o-bolt')
                ->requiresConfirmation()
                ->action(function (): void {
                    $provisioned = $this->provisionSubscriber();

                    if ($provisioned) {
                        Notification::make()
                            ->success()
                            ->title('Provisioning complete')
                            ->body('Subscriber provisioned via ' . ($this->record->provisioning_method?->label() ?? 'RADIUS') . '.')
                            ->send();
                    }
                }),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
