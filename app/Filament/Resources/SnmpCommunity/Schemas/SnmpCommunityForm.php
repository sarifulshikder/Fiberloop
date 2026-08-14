<?php

namespace App\Filament\Resources\SnmpCommunity\Schemas;

use App\Models\NetworkDevice;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SnmpCommunityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('SNMP Community Configuration')->schema([
                    Grid::make(2)->schema([
                        Select::make('network_device_id')
                            ->label('Network Device')
                            ->options(NetworkDevice::query()->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->placeholder('Select a network device'),

                        TextInput::make('community_name')
                            ->label('Community Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. public, private, mycommunity'),
                    ]),
                    Grid::make(2)->schema([
                        Select::make('access_right')
                            ->label('Access Right')
                            ->options([
                                'read-only' => 'Read-Only',
                                'read-write' => 'Read-Write',
                            ])
                            ->required()
                            ->default('read-only'),

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
                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->maxLength(1000)
                        ->placeholder('Optional description of this SNMP community'),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Enable or disable this SNMP community'),
                ]),
            ]);
    }
}
