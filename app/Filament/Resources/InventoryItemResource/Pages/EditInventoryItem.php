<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InventoryItemResource\Schemas\InventoryItemForm;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditInventoryItem extends EditRecord
{
    protected static string $resource = InventoryItemResource::class;

    public function form(Schema $form): Schema
    {
        return $form
            ->schema(InventoryItemForm::schema());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        // Convert BDT amounts to poysha (integer)
        if (isset($data['purchase_price'])) {
            $data['purchase_price'] = (int) round($data['purchase_price'] * 100);
        }
        if (isset($data['selling_price'])) {
            $data['selling_price'] = (int) round($data['selling_price'] * 100);
        }

        return $data;
    }
}
