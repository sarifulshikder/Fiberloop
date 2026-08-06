<?php

namespace App\Filament\Resources\ProcurementResource\Pages;

use App\Filament\Resources\ProcurementResource;
use App\Filament\Resources\ProcurementResource\Tables\ProcurementsTable;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListProcurements extends ListRecords
{
    protected static string $resource = ProcurementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns(ProcurementsTable::columns())
            ->filters(ProcurementsTable::filters())
            ->actions(ProcurementsTable::actions())
            ->bulkActions(ProcurementsTable::bulkActions())
            ->defaultSort('id', 'desc')
            ->paginated([15, 30, 50, 100]);
    }
}
