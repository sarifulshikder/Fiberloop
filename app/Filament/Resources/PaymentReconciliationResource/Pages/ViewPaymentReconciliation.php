<?php

namespace App\Filament\Resources\PaymentReconciliationResource\Pages;

use App\Filament\Resources\PaymentReconciliationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentReconciliation extends ViewRecord
{
    protected static string $resource = PaymentReconciliationResource::class;

    public function getTitle(): string
    {
        return 'View Payment Reconciliation';
    }
}
