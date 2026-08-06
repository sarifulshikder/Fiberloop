<?php

namespace App\Filament\Resources\StockTransactionResource\Tables;

use App\Enums\InventoryStatus;
use App\Enums\StockTransactionReason;
use App\Enums\StockTransactionType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class StockTransactionsTable
{
    public static function columns(): array
    {
        return [
            TextColumn::make('id')
                ->label('ID')
                ->sortable()
                ->searchable(),
            TextColumn::make('inventory_item.name')
                ->label('Item')
                ->sortable()
                ->searchable(),
            SelectColumn::make('transaction_type')
                ->label('Type')
                ->options(StockTransactionType::class)
                ->sortable()
                ->searchable()
                ->color(fn (\App\Models\StockTransaction $record): string => $record->transaction_type->color()),
            SelectColumn::make('reason')
                ->label('Reason')
                ->options(StockTransactionReason::class)
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
            SelectColumn::make('previous_status')
                ->label('From Status')
                ->options(InventoryStatus::class)
                ->sortable(),
            SelectColumn::make('new_status')
                ->label('To Status')
                ->options(InventoryStatus::class)
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
                ->options(\App\Models\Customer::query()->pluck('full_name', 'id'))
                ->searchable()
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
