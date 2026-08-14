<?php

namespace App\Filament\Resources\SnmpCommunity\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SnmpCommunitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                CheckboxColumn::make('id')
                    ->label('Select')
                    ->width(40),
                TextColumn::make('networkDevice.name')
                    ->label('Network Device')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('community_name')
                    ->label('Community Name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('access_right')
                    ->label('Access Right')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'read-only' => 'success',
                        'read-write' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('snmp_version')
                    ->label('SNMP Version')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('network_device_id')
                    ->label('Network Device')
                    ->options(\App\Models\NetworkDevice::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('access_right')
                    ->label('Access Right')
                    ->options([
                        'read-only' => 'Read-Only',
                        'read-write' => 'Read-Write',
                    ]),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('network_device_id', 'asc')
            ->defaultSort('community_name', 'asc');
    }
}
