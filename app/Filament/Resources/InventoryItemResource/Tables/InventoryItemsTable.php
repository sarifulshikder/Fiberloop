<?php

namespace App\Filament\Resources\InventoryItemResource\Tables;

use App\Enums\InventoryStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class InventoryItemsTable
{
    public static function columns(): array
    {
        return [
            TextColumn::make('id')
                ->label('ID')
                ->sortable()
                ->searchable(),
            TextColumn::make('name')
                ->label('Name')
                ->sortable()
                ->searchable(),
            TextColumn::make('item_type')
                ->label('Type')
                ->sortable()
                ->searchable(),
            TextColumn::make('brand')
                ->label('Brand')
                ->sortable()
                ->searchable(),
            TextColumn::make('model')
                ->label('Model')
                ->sortable()
                ->searchable(),
            TextColumn::make('serial_number')
                ->label('Serial Number')
                ->sortable()
                ->searchable(),
            TextColumn::make('mac_address')
                ->label('MAC Address')
                ->sortable()
                ->searchable(),
            TextColumn::make('asset_tag')
                ->label('Asset Tag')
                ->sortable()
                ->searchable(),
            SelectColumn::make('status')
                ->label('Status')
                ->options(InventoryStatus::class)
                ->sortable()
                ->searchable()
                ->color(fn (\App\Models\InventoryItem $record): string => $record->status->color()),
            TextColumn::make('warehouse')
                ->label('Warehouse')
                ->sortable()
                ->searchable(),
            TextColumn::make('bin_location')
                ->label('Bin')
                ->sortable()
                ->searchable(),
            TextColumn::make('customer.full_name')
                ->label('Assigned Customer')
                ->sortable()
                ->searchable(),
            TextColumn::make('purchase_price')
                ->label('Purchase Price')
                ->sortable()
                ->formatStateUsing(fn (int $state): string => 'à§³' . number_format($state / 100, 2)),
            TextColumn::make('purchase_date')
                ->label('Purchased')
                ->date()
                ->sortable(),
            TextColumn::make('warranty_end')
                ->label('Warranty Expires')
                ->date()
                ->sortable()
                ->color(fn (\App\Models\InventoryItem $record): string => $record->warranty_end && $record->warranty_end->isBefore(now()) ? 'danger' : 'success'),
            TextColumn::make('assigned_at')
                ->label('Assigned')
                ->dateTime()
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
            SelectFilter::make('status')
                ->label('Status')
                ->options(InventoryStatus::class)
                ->multiple(),
            SelectFilter::make('item_type')
                ->label('Item Type')
                ->options([
                    'ONT' => 'ONT/ONU',
                    'OLT' => 'OLT',
                    'router' => 'Router',
                    'switch' => 'Switch',
                    'cable' => 'Cable',
                    'accessory' => 'Accessory',
                    'other' => 'Other',
                ])
                ->multiple(),
            SelectFilter::make('warehouse')
                ->label('Warehouse')
                ->options(fn () => \App\Models\InventoryItem::query()->distinct('warehouse')->pluck('warehouse', 'warehouse')->filter()->toArray())
                ->multiple(),
            SelectFilter::make('brand')
                ->label('Brand')
                ->options(fn () => \App\Models\InventoryItem::query()->distinct('brand')->pluck('brand', 'brand')->filter()->toArray())
                ->multiple(),
            TernaryFilter::make('is_in_stock')
                ->label('In Stock')
                ->queries(
                    true: fn ($query) => $query->where('status', InventoryStatus::IN_STOCK->value),
                    false: fn ($query) => $query->where('status', '!=', InventoryStatus::IN_STOCK->value),
                ),
            TernaryFilter::make('is_assigned')
                ->label('Assigned')
                ->queries(
                    true: fn ($query) => $query->where('status', InventoryStatus::ASSIGNED->value),
                    false: fn ($query) => $query->where('status', '!=', InventoryStatus::ASSIGNED->value),
                ),
            TernaryFilter::make('warranty_expiring')
                ->label('Warranty Expiring (30 days)')
                ->queries(
                    true: fn ($query) => $query->warrantyExpiring(30),
                ),
            TernaryFilter::make('warranty_expired')
                ->label('Warranty Expired')
                ->queries(
                    true: fn ($query) => $query->where('warranty_end', '<=', now()->toDateString())->whereNotNull('warranty_end'),
                ),
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
