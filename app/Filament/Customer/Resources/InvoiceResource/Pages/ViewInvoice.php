<?php

namespace App\Filament\Customer\Resources\InvoiceResource\Pages;

use App\Filament\Customer\Resources\InvoiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('Download PDF')
                ->label('Download Invoice')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn ($record) => route('invoice.pdf', $record))
                ->openUrlInNewTab(),
            Action::make('Pay Now')
                ->label('Pay Invoice')
                ->icon('heroicon-o-credit-card')
                ->url(fn ($record) => route('customer.payment.create', ['invoice_id' => $record->id]))
                ->visible(fn ($record) => $record->outstanding_amount > 0 && $record->status !== 'paid'),
        ];
    }
}
