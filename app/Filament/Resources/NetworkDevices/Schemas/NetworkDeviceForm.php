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
                    Grid::make(3)->schema([
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

                Section::make('Authentication & Management Settings')->schema([
                    Select::make('management_protocol')
                        ->label('Management Protocol')
                        ->options(NetworkManagementProtocol::options())
                        ->default(NetworkManagementProtocol::SNMP->value)
                        ->helperText('SSH CLI reads ONU optical power/descriptions on every OLT brand; SNMP works when the vendor MIB is available.')
                        ->live(),
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
                    Grid::make(3)->schema([
                        TextInput::make('snmp_community')
                            ->label('SNMP Community')
                            ->maxLength(255)
                            ->default('public')
                            ->placeholder('e.g. public')
                            ->hidden(fn (Get $get) => $get('management_protocol') === NetworkManagementProtocol::SSH->value),

                        Select::make('snmp_version')
                            ->label('SNMP Version')
                            ->options([
                                'v1' => 'v1',
                                'v2c' => 'v2c',
                                'v3' => 'v3',
                            ])
                            ->default('v2c')
                            ->required()
                            ->hidden(fn (Get $get) => $get('management_protocol') === NetworkManagementProtocol::SSH->value),

                        TextInput::make('snmp_port')
                            ->label('SNMP Port')
                            ->numeric()
                            ->default(161)
                            ->required()
                            ->placeholder('e.g. 161')
                            ->hidden(fn (Get $get) => $get('management_protocol') === NetworkManagementProtocol::SSH->value),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('configuration.telnet_port')
                            ->label('Telnet CLI Port')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->placeholder('e.g. 223')
                            ->helperText('Port for the vendor telnet CLI (VSOL). Leave blank for SSH-only OLTs.')
                            ->hidden(fn (Get $get) => $get('management_protocol') === NetworkManagementProtocol::SNMP->value),
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
