<?php

namespace App\Filament\Resources\PaymentReconciliationResource\Pages;

use App\Filament\Resources\PaymentReconciliationResource;
use Filament\Resources\Pages\EditRecord;

class EditPaymentReconciliation extends EditRecord
{
    protected static string $resource = PaymentReconciliationResource::class;

    public function getTitle(): string
    {
        return 'Edit Payment Reconciliation';
    }
}
