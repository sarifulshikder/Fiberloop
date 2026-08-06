<?php

namespace App\Filament\Resources\StockTransactionResource\Pages;

use App\Filament\Resources\StockTransactionResource;
use App\Filament\Resources\StockTransactionResource\Schemas\StockTransactionForm;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditStockTransaction extends EditRecord
{
    protected static string $resource = StockTransactionResource::class;

    public function form(Schema $form): Schema
    {
        return $form
            ->schema(StockTransactionForm::schema());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        // Convert BDT amounts to poysha (integer)
        if (isset($data['unit_cost'])) {
            $data['unit_cost'] = (int) round($data['unit_cost'] * 100);
        }
        if (isset($data['total_cost'])) {
            $data['total_cost'] = (int) round($data['total_cost'] * 100);
        }

        return $data;
    }
}
