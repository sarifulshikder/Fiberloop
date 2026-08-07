<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryItemResource\Pages;
use App\Filament\Resources\InventoryItemResource\RelationManagers;
use App\Filament\Resources\InventoryItemResource\Schemas;
use App\Filament\Resources\InventoryItemResource\Tables;
use App\Models\InventoryItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class InventoryItemResource extends Resource
{
    protected static ?string $model = InventoryItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';
    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Inventory Items';
    protected static ?string $pluralModelLabel = 'Inventory Items';
    protected static ?string $modelLabel = 'Inventory Item';
    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'serial_number', 'mac_address', 'asset_tag', 'barcode'];
    }

    public static function getDescription(): string
    {
        return 'Manage physical equipment inventory from procurement through customer assignment to return/retirement';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema(Schemas\InventoryItemForm::schema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(Tables\InventoryItemsTable::columns())
            ->filters(Tables\InventoryItemsTable::filters())
            ->actions(Tables\InventoryItemsTable::actions())
            ->bulkActions(Tables\InventoryItemsTable::bulkActions())
            ->defaultSort('id', 'desc')
            ->paginated([15, 30, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InventoryItemRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryItems::route('/'),
            'create' => Pages\CreateInventoryItem::route('/create'),
            'view' => Pages\ViewInventoryItem::route('/{record}'),
            'edit' => Pages\EditInventoryItem::route('/{record}/edit'),
        ];
    }

    public static function getNavigationSort(): int
    {
        return 1;
    }
}
