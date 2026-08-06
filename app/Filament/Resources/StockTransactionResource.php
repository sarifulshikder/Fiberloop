<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockTransactionResource\Pages;
use App\Filament\Resources\StockTransactionResource\Schemas;
use App\Filament\Resources\StockTransactionResource\Tables;
use App\Models\StockTransaction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class StockTransactionResource extends Resource
{
    protected static ?string $model = StockTransaction::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static \UnitEnum|string|null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Stock Transactions';
    protected static ?string $pluralModelLabel = 'Stock Transactions';
    protected static ?string $modelLabel = 'Stock Transaction';
    protected static ?int $navigationSort = 2;

    public static function getGloballySearchableAttributes(): array
    {
        return ['reference_number', 'notes'];
    }

    public static function getDescription(): string
    {
        return 'Track all inventory movements with full audit trail of who moved what, when, and why';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema(Schemas\StockTransactionForm::schema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(Tables\StockTransactionsTable::columns())
            ->filters(Tables\StockTransactionsTable::filters())
            ->actions(Tables\StockTransactionsTable::actions())
            ->bulkActions(Tables\StockTransactionsTable::bulkActions())
            ->defaultSort('created_at', 'desc')
            ->paginated([15, 30, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockTransactions::route('/'),
            'create' => Pages\CreateStockTransaction::route('/create'),
            'view' => Pages\ViewStockTransaction::route('/{record}'),
            'edit' => Pages\EditStockTransaction::route('/{record}/edit'),
        ];
    }

    public static function getNavigationSort(): int
    {
        return 2;
    }
}
