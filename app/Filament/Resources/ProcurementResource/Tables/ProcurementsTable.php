<?php

namespace App\Filament\Resources\ProcurementResource\Tables;

use App\Enums\ProcurementStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class ProcurementsTable
{
    public static function columns(): array
    {
        return [
            TextColumn::make('id')
                ->label('ID')
                ->sortable()
                ->searchable(),
            TextColumn::make('po_number')
                ->label('PO Number')
                ->sortable()
                ->searchable(),
            TextColumn::make('title')
                ->label('Title')
                ->sortable()
                ->searchable(),
            SelectColumn::make('status')
                ->label('Status')
                ->options(ProcurementStatus::class)
                ->sortable()
                ->searchable()
                ->color(fn (\App\Models\Procurement $record): string => $record->status->color()),
            TextColumn::make('supplier.name')
                ->label('Supplier')
                ->sortable()
                ->searchable(),
            TextColumn::make('total_amount')
                ->label('Total Amount')
                ->sortable()
                ->formatStateUsing(fn (int $state): string => 'à§³' . number_format($state / 100, 2)),
            TextColumn::make('order_date')
                ->label('Ordered')
                ->date()
                ->sortable(),
            TextColumn::make('expected_delivery_date')
                ->label('Expected')
                ->date()
                ->sortable(),
            TextColumn::make('actual_delivery_date')
                ->label('Delivered')
                ->date()
                ->sortable(),
            IconColumn::make('is_overdue')
                ->label('Overdue')
                ->boolean()
                ->trueIcon('heroicon-o-exclamation-triangle')
                ->falseIcon('heroicon-o-check-circle')
                ->trueColor('danger')
                ->falseColor('success'),
            TextColumn::make('tracking_number')
                ->label('Tracking')
                ->sortable()
                ->searchable(),
            TextColumn::make('priority')
                ->label('Priority')
                ->sortable()
                ->badge()
                ->color(fn (string $state): string => match($state) {
                    'urgent' => 'danger',
                    'high' => 'warning',
                    'medium' => 'info',
                    'low' => 'success',
                    default => 'secondary',
                }),
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
                ->options(ProcurementStatus::class)
                ->multiple(),
            SelectFilter::make('priority')
                ->label('Priority')
                ->options([
                    'low' => 'Low',
                    'medium' => 'Medium',
                    'high' => 'High',
                    'urgent' => 'Urgent',
                ])
                ->multiple(),
            TernaryFilter::make('is_overdue')
                ->label('Overdue')
                ->queries(
                    true: fn ($query) => $query->overdue(),
                    false: fn ($query) => $query->where('status', ProcurementStatus::RECEIVED->value),
                ),
            TernaryFilter::make('is_active')
                ->label('Active')
                ->queries(
                    true: fn ($query) => $query->whereIn('status', [
                        ProcurementStatus::DRAFT->value,
                        ProcurementStatus::PENDING_APPROVAL->value,
                        ProcurementStatus::APPROVED->value,
                        ProcurementStatus::ORDERED->value,
                        ProcurementStatus::PARTIALLY_RECEIVED->value,
                    ]),
                    false: fn ($query) => $query->whereIn('status', [
                        ProcurementStatus::RECEIVED->value,
                        ProcurementStatus::CANCELLED->value,
                    ]),
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
