<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcurementResource\Pages;
use App\Filament\Resources\ProcurementResource\Schemas;
use App\Filament\Resources\ProcurementResource\Tables;
use App\Models\Procurement;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ProcurementResource extends Resource
{
    protected static ?string $model = Procurement::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static \UnitEnum|string|null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Procurements';
    protected static ?string $pluralModelLabel = 'Procurements';
    protected static ?string $modelLabel = 'Procurement';
    protected static ?int $navigationSort = 3;

    public static function getGloballySearchableAttributes(): array
    {
        return ['po_number', 'title', 'description', 'tracking_number'];
    }

    public static function getDescription(): string
    {
        return 'Track purchase orders from creation through approval, ordering, and receipt';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema(Schemas\ProcurementForm::schema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(Tables\ProcurementsTable::columns())
            ->filters(Tables\ProcurementsTable::filters())
            ->actions(Tables\ProcurementsTable::actions())
            ->bulkActions(Tables\ProcurementsTable::bulkActions())
            ->defaultSort('id', 'desc')
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
            'index' => Pages\ListProcurements::route('/'),
            'create' => Pages\CreateProcurement::route('/create'),
            'view' => Pages\ViewProcurement::route('/{record}'),
            'edit' => Pages\EditProcurement::route('/{record}/edit'),
        ];
    }

    public static function getNavigationSort(): int
    {
        return 3;
    }
}
