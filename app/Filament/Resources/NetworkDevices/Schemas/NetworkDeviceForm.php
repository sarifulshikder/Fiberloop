<?php

namespace App\Filament\Resources\NetworkDevices\Schemas;

use App\Enums\DeviceVendor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NetworkDeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Device Identity')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label('Device Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Core Router Dhaka'),

                        Select::make('vendor')
                            ->label('Vendor')
                            ->options(DeviceVendor::options())
                            ->required(),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('model')
                            ->label('Model')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. CCR2004-16G-2S+'),

                        TextInput::make('serial_number')
                            ->label('Serial Number')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('e.g. SN12345678'),
                    ]),
                ]),

                Section::make('Network Connectivity')->schema([
                    Grid::make(3)->schema([
                        TextInput::make('ip_address')
                            ->label('IP Address')
                            ->required()
                            ->ip()
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g. 192.168.88.1'),

                        TextInput::make('hostname')
                            ->label('Hostname')
                            ->maxLength(255)
                            ->placeholder('e.g. router.fiberloop.net'),

                        TextInput::make('port')
                            ->label('API/SSH Port')
                            ->numeric()
                            ->default(8728)
                            ->required()
                            ->placeholder('e.g. 8728'),
                    ]),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),

                Section::make('Authentication & SNMP Settings')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('username')
                            ->label('API/SSH Username')
                            ->maxLength(255)
                            ->placeholder('e.g. admin'),

                        TextInput::make('password')
                            ->label('API/SSH Password')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('snmp_community')
                            ->label('SNMP Community')
                            ->maxLength(255)
                            ->default('public')
                            ->placeholder('e.g. public'),

                        Select::make('snmp_version')
                            ->label('SNMP Version')
                            ->options([
                                'v1' => 'v1',
                                'v2c' => 'v2c',
                                'v3' => 'v3',
                            ])
                            ->default('v2c')
                            ->required(),
                    ]),
                ]),

                Section::make('Location & Additional Info')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('location')
                            ->label('Location (POP Name)')
                            ->maxLength(255)
                            ->placeholder('e.g. Dhaka Central POP'),

                        TextInput::make('address')
                            ->label('Physical Address')
                            ->maxLength(500)
                            ->placeholder('e.g. 123 Main St, Dhaka'),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('latitude')
                            ->label('Latitude')
                            ->maxLength(50)
                            ->placeholder('e.g. 23.8103'),

                        TextInput::make('longitude')
                            ->label('Longitude')
                            ->maxLength(50)
                            ->placeholder('e.g. 90.4125'),
                    ]),
                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3)
                        ->maxLength(1000),
                ]),
            ]);
    }
}
