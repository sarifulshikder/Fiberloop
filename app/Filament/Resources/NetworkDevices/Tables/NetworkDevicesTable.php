<?php

namespace App\Filament\Resources\NetworkDevices\Tables;

use App\Enums\DeviceVendor;
use App\Models\NetworkDevice;
use App\Services\Network\DeviceReachabilityService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\CheckboxColumn;
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
                CheckboxColumn::make('id')
                    ->label('Select')
                    ->width(40),
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
                        'vsol' => 'primary',
                        'bdcom' => 'danger',
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

                TextColumn::make('management_protocol')
                    ->label('Protocol')
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'ssh' => 'success',
                        'api' => 'warning',
                        default => 'info',
                    })
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
                Action::make('checkNow')
                    ->label('Check Now')
                    ->icon('heroicon-o-signal')
                    ->color('primary')
                    ->tooltip('Manually check if this device is online/reachable')
                    ->action(function (NetworkDevice $record, $livewire): void {
                        $result = app(DeviceReachabilityService::class)->check($record);

                        if ($result['reachable']) {
                            $pingText = $result['ping_ms'] !== null ? " ({$result['ping_ms']} ms)" : '';
                            Notification::make()
                                ->title("{$record->name} is online")
                                ->body("Status: {$result['status']}{$pingText}")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title("{$record->name} is offline")
                                ->body('The device did not respond to ping.')
                                ->danger()
                                ->send();
                        }

                        $livewire->resetTable();
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }
}
