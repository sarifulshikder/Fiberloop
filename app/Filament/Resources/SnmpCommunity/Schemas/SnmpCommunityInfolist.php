<?php

namespace App\Filament\Resources\SnmpCommunity\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SnmpCommunityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('SNMP Community Details')->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('networkDevice.name')
                            ->label('Network Device'),

                        TextEntry::make('community_name')
                            ->label('Community Name'),
                    ]),
                    Grid::make(2)->schema([
                        TextEntry::make('access_right')
                            ->label('Access Right')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'read-only' => 'success',
                                'read-write' => 'warning',
                                default => 'gray',
                            }),

                        TextEntry::make('snmp_version')
                            ->label('SNMP Version')
                            ->badge()
                            ->color('info'),
                    ]),
                    TextEntry::make('description')
                        ->label('Description')
                        ->placeholder('No description provided'),
                ]),

                Section::make('Status')->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('is_active')
                            ->label('Active Status')
                            ->boolean(),

                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                    ]),
                ]),
            ]);
    }
}
