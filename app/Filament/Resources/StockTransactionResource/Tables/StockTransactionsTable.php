<?php

namespace App\Filament\Resources\StockTransactionResource\Tables;

use App\Enums\StockTransactionReason;
use App\Enums\StockTransactionType;
use App\Models\Customer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

class StockTransactionsTable
{
    public static function columns(): array
    {
        return [
            CheckboxColumn::make('id')
                ->label('Select')
                ->width(40),
            TextColumn::make('id')
                ->label('ID')
                ->sortable()
                ->searchable(),
            TextColumn::make('inventory_item.name')
                ->label('Item')
                ->sortable()
                ->searchable(),
            TextColumn::make('transaction_type')
                ->label('Type')
                ->badge()
                ->sortable()
                ->searchable()
                ->color(fn (\App\Models\StockTransaction $record): string => $record->transaction_type->color()),
            TextColumn::make('reason')
                ->label('Reason')
                ->badge()
                ->sortable()
                ->searchable(),
            TextColumn::make('reference_number')
                ->label('Reference')
                ->sortable()
                ->searchable(),
            TextColumn::make('quantity')
                ->label('Qty')
                ->sortable()
                ->numeric(),
            TextColumn::make('unit_cost')
                ->label('Unit Cost')
                ->sortable()
                ->formatStateUsing(fn (int $state): string => 'à§³' . number_format($state / 100, 2)),
            TextColumn::make('total_cost')
                ->label('Total Cost')
                ->sortable()
                ->formatStateUsing(fn (int $state): string => 'à§³' . number_format($state / 100, 2)),
            TextColumn::make('customer.full_name')
                ->label('Customer')
                ->sortable()
                ->searchable(),
            TextColumn::make('user.name')
                ->label('User')
                ->sortable()
                ->searchable(),
            TextColumn::make('previous_status')
                ->label('From Status')
                ->badge()
                ->sortable(),
            TextColumn::make('new_status')
                ->label('To Status')
                ->badge()
                ->sortable(),
            TextColumn::make('created_at')
                ->label('Created')
                ->dateTime()
                ->sortable(),
        ];
    }

    public static function filters(): array
    {
        return [
            SelectFilter::make('transaction_type')
                ->label('Transaction Type')
                ->options(StockTransactionType::class)
                ->multiple(),
            SelectFilter::make('reason')
                ->label('Reason')
                ->options(StockTransactionReason::class)
                ->multiple(),
            SelectFilter::make('inventory_item_id')
                ->label('Inventory Item')
                ->options(\App\Models\InventoryItem::query()->pluck('name', 'id'))
                ->searchable()
                ->multiple(),
            TernaryFilter::make('is_incoming')
                ->label('Incoming')
                ->queries(
                    true: fn ($query) => $query->incoming(),
                    false: fn ($query) => $query->outgoing(),
                ),
            SelectFilter::make('customer_id')
                ->label('Customer')
                ->relationship(
                    name: 'customer',
                    titleAttribute: 'first_name',
                    modifyQueryUsing: fn (Builder $query) => $query->orderBy('first_name')->orderBy('last_name'),
                )
                ->getOptionLabelFromRecordUsing(
                    fn (Customer $record) => "{$record->first_name} {$record->last_name}"
                )
                ->searchable(['first_name', 'last_name', 'phone'])
                ->multiple(),
            SelectFilter::make('user_id')
                ->label('User')
                ->options(\App\Models\User::query()->pluck('name', 'id'))
                ->searchable()
                ->multiple(),
        ];
    }

    public static function actions(): array
    {
        return [
            ViewAction::make(),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    public static function bulkActions(): array
    {
        return [
            BulkActionGroup::make([
                ExportBulkAction::make(),
                DeleteBulkAction::make(),
            ]),
        ];
    }
}
