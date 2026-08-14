<?php

namespace App\Filament\Resources\SnmpTrap\Schemas;

use App\Models\NetworkDevice;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SnmpTrapForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('SNMP Trap Configuration')->schema([
                    Grid::make(2)->schema([
                        Select::make('network_device_id')
                            ->label('Network Device')
                            ->options(NetworkDevice::query()->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->placeholder('Select a network device'),

                        TextInput::make('host_ip')
                            ->label('Trap Host IP')
                            ->required()
                            ->ip()
                            ->placeholder('e.g. 192.168.1.100'),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('udp_port')
                            ->label('UDP Port')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->default(162)
                            ->required()
                            ->placeholder('e.g. 162'),

                        Select::make('snmp_version')
                            ->label('SNMP Version')
                            ->options([
                                'v1' => 'v1',
                                'v2c' => 'v2c',
                                'v3' => 'v3',
                            ])
                            ->required()
                            ->default('v2c'),
                    ]),
                    TextInput::make('community_name')
                        ->label('Community Name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. public'),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->maxLength(1000)
                        ->placeholder('Optional description of this SNMP trap'),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Enable or disable this SNMP trap'),
                ]),
            ]);
    }
}
