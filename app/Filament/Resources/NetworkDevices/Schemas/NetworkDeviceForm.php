<?php

namespace App\Filament\Resources\NetworkDevices\Schemas;

use App\Enums\DeviceVendor;
use App\Enums\NetworkManagementProtocol;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                    Grid::make(2)->schema([
                        TextInput::make('ip_address')
                            ->label('IP Address')
                            ->required()
                            ->ip()
                            ->placeholder('e.g. 192.168.88.1')
                            ->hint('Multiple devices may share an IP when using port forwarding.'),

                        TextInput::make('hostname')
                            ->label('Hostname')
                            ->maxLength(255)
                            ->placeholder('e.g. router.fiberloop.net'),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('username')
                            ->label('Username')
                            ->maxLength(255)
                            ->placeholder('e.g. admin'),

                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ]),
                    Grid::make(3)->schema([
                        TextInput::make('port')
                            ->label('API Port')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->helperText('MikroTik RouterOS API port. Leave blank unless needed.')
                            ->placeholder('e.g. 8728'),

                        TextInput::make('ssh_port')
                            ->label('SSH Port')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->helperText('SSH CLI port for CLI-managed OLTs. Leave blank unless needed.')
                            ->placeholder('e.g. 22'),

                        TextInput::make('telnet_port')
                            ->label('Telnet Port')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->placeholder('e.g. 23')
                            ->helperText('Telnet CLI port for VSOL/BDCOM OLTs. Leave blank unless needed.'),
                    ]),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),

                Section::make('Management Protocol')->schema([
                    Select::make('management_protocol')
                        ->label('Management Protocol')
                        ->options(NetworkManagementProtocol::options())
                        ->default(NetworkManagementProtocol::SNMP->value)
                        ->helperText('API (RouterOS) talks to MikroTik on the API port; SSH CLI reads ONU optical power/descriptions on every OLT brand; SNMP works when the vendor MIB is available.')
                        ->live(),
                    Grid::make(2)->schema([
                        TextInput::make('snmp_community')
                            ->label('SNMP Community')
                            ->maxLength(255)
                            ->default('public')
                            ->placeholder('e.g. public')
                            ->hidden(fn (Get $get) => $get('management_protocol') !== NetworkManagementProtocol::SNMP->value),

                        Select::make('snmp_version')
                            ->label('SNMP Version')
                            ->options([
                                'v1' => 'v1',
                                'v2c' => 'v2c',
                                'v3' => 'v3',
                            ])
                            ->default('v2c')
                            ->required()
                            ->hidden(fn (Get $get) => $get('management_protocol') !== NetworkManagementProtocol::SNMP->value),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('snmp_port')
                            ->label('SNMP Port')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->placeholder('e.g. 161')
                            ->helperText('Leave blank unless the device is SNMP-managed.')
                            ->hidden(fn (Get $get) => $get('management_protocol') !== NetworkManagementProtocol::SNMP->value),
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
