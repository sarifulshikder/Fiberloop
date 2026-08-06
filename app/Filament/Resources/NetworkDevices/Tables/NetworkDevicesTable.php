<?php

namespace App\Filament\Resources\NetworkDevices\Tables;

use App\Enums\DeviceVendor;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NetworkDevicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Device Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('vendor')
                    ->label('Vendor')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'mikrotik' => 'success',
                        'huawei' => 'info',
                        'zte' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('model')
                    ->label('Model')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_reachable')
                    ->label('Reachable')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('last_checked_at')
                    ->label('Last Checked')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('vendor')
                    ->options(DeviceVendor::options()),

                SelectFilter::make('is_active')
                    ->label('Active State')
                    ->options([
                        '1' => 'Active Only',
                        '0' => 'Inactive Only',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }
}
