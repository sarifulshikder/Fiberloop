<?php

namespace App\Filament\Resources\StockTransactionResource\Pages;

use App\Filament\Resources\StockTransactionResource;
use App\Filament\Resources\StockTransactionResource\Tables\StockTransactionsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListStockTransactions extends ListRecords
{
    protected static string $resource = StockTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(StockTransactionsTable::columns())
            ->filters(StockTransactionsTable::filters())
            ->actions(StockTransactionsTable::actions())
            ->bulkActions(StockTransactionsTable::bulkActions())
            ->defaultSort('id', 'desc')
            ->paginated([15, 30, 50, 100]);
    }
}
