<?php

namespace App\Filament\Resources\ProcurementResource\Pages;

use App\Filament\Resources\ProcurementResource;
use App\Filament\Resources\ProcurementResource\Schemas\ProcurementForm;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;

class CreateProcurement extends CreateRecord
{
    protected static string $resource = ProcurementResource::class;

    public function form(Schema $form): Schema
    {
        return $form
            ->schema(ProcurementForm::schema());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uuid'] = \Illuminate\Support\Str::uuid();
        $data['tenant_id'] = auth()->user()?->tenant_id;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        // Convert BDT amounts to poysha (integer)
        if (isset($data['subtotal'])) {
            $data['subtotal'] = (int) round($data['subtotal'] * 100);
        }
        if (isset($data['tax_amount'])) {
            $data['tax_amount'] = (int) round($data['tax_amount'] * 100);
        }
        if (isset($data['shipping_cost'])) {
            $data['shipping_cost'] = (int) round($data['shipping_cost'] * 100);
        }
        if (isset($data['total_amount'])) {
            $data['total_amount'] = (int) round($data['total_amount'] * 100);
        }

        return $data;
    }
}
