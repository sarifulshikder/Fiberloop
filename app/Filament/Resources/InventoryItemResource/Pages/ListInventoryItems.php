<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InventoryItemResource\Tables\InventoryItemsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(InventoryItemsTable::columns())
            ->filters(InventoryItemsTable::filters())
            ->actions(InventoryItemsTable::actions())
            ->bulkActions(InventoryItemsTable::bulkActions())
            ->defaultSort('id', 'desc')
            ->paginated([15, 30, 50, 100]);
    }
}
