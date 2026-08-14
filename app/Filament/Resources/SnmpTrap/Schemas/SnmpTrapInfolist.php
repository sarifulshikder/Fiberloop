<?php

namespace App\Filament\Resources\SnmpTrap\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SnmpTrapInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('SNMP Trap Details')->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('networkDevice.name')
                            ->label('Network Device'),

                        TextEntry::make('host_ip')
                            ->label('Trap Host IP'),
                    ]),
                    Grid::make(2)->schema([
                        TextEntry::make('udp_port')
                            ->label('UDP Port'),

                        TextEntry::make('community_name')
                            ->label('Community Name'),
                    ]),
                    Grid::make(2)->schema([
                        TextEntry::make('snmp_version')
                            ->label('SNMP Version')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('is_active')
                            ->label('Active Status')
                            ->boolean(),
                    ]),
                    TextEntry::make('description')
                        ->label('Description')
                        ->placeholder('No description provided'),
                ]),

                Section::make('Status')->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime(),
                    ]),
                ]),
            ]);
    }
}
