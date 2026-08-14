<?php

namespace App\Filament\Resources\NetworkDevices\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NetworkDeviceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Device Identity')->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('name')
                            ->label('Device Name'),

                        TextEntry::make('vendor')
                            ->label('Vendor')
                            ->badge()
                            ->color(fn ($state) => match ($state?->value ?? $state) {
                                'mikrotik' => 'success',
                                'huawei' => 'info',
                                'zte' => 'warning',
                                default => 'gray',
                            }),
                    ]),
                    Grid::make(2)->schema([
                        TextEntry::make('model')
                            ->label('Model'),

                        TextEntry::make('serial_number')
                            ->label('Serial Number')
                            ->placeholder('N/A'),
                    ]),
                ]),

                Section::make('Network & Status')->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('ip_address')
                            ->label('IP Address'),

                        TextEntry::make('hostname')
                            ->label('Hostname')
                            ->placeholder('N/A'),

                        TextEntry::make('port')
                            ->label('API/SSH Port'),

                        TextEntry::make('management_protocol')
                            ->label('Protocol')
                            ->badge()
                            ->color(fn ($state) => match ($state?->value ?? $state) {
                                'ssh' => 'success',
                                default => 'info',
                            }),

                        TextEntry::make('snmp_version')
                            ->label('SNMP Version')
                            ->placeholder('N/A'),

                        TextEntry::make('snmp_port')
                            ->label('SNMP Port')
                            ->placeholder('N/A'),

                        TextEntry::make('snmp_community')
                            ->label('SNMP Community')
                            ->placeholder('N/A'),
                    ]),
                    Grid::make(3)->schema([
                        IconEntry::make('is_active')
                            ->label('Active Status')
                            ->boolean(),

                        IconEntry::make('is_reachable')
                            ->label('Live Reachable')
                            ->boolean(),

                        TextEntry::make('last_checked_at')
                            ->label('Last Checked At')
                            ->dateTime()
                            ->placeholder('Never'),
                    ]),
                ]),

                Section::make('Location & Notes')->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('location')
                            ->label('Location POP')
                            ->placeholder('N/A'),

                        TextEntry::make('address')
                            ->label('Physical Address')
                            ->placeholder('N/A'),
                    ]),
                    Grid::make(2)->schema([
                        TextEntry::make('latitude')
                            ->label('Latitude')
                            ->placeholder('N/A'),

                        TextEntry::make('longitude')
                            ->label('Longitude')
                            ->placeholder('N/A'),
                    ]),
                    TextEntry::make('notes')
                        ->label('Notes')
                        ->placeholder('No notes recorded.'),
                ]),
            ]);
    }
}
