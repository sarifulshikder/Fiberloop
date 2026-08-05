<?php

namespace App\Filament\Resources\PaymentReconciliationResource\Pages;

use App\Filament\Resources\PaymentReconciliationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentReconciliation extends CreateRecord
{
    protected static string $resource = PaymentReconciliationResource::class;

    public function getTitle(): string
    {
        return 'Create Payment Reconciliation';
    }
}
