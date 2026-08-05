<?php

namespace App\Filament\Resources\PaymentReconciliationResource\Pages;

use App\Filament\Resources\PaymentReconciliationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentReconciliations extends ListRecords
{
    protected static string $resource = PaymentReconciliationResource::class;

    public function getTitle(): string
    {
        return 'Payment Reconciliations';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
