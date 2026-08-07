<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IpPoolResource\Pages\CreateIpPool;
use App\Filament\Resources\IpPoolResource\Pages\EditIpPool;
use App\Filament\Resources\IpPoolResource\Pages\ListIpPools;
use App\Models\IpPool;
use App\Models\NetworkDevice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IpPoolResource extends Resource
{
    protected static ?string $model = IpPool::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';
    protected static string|\UnitEnum|null $navigationGroup = 'Network';
    protected static ?string $navigationLabel = 'IP Pools';
    protected static ?int $navigationSort = 6;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Pool Details')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    \Filament\Forms\Components\Select::make('type')
                        ->options([
                            'static'  => 'Static',
                            'dhcp'    => 'DHCP',
                            'hotspot' => 'Hotspot',
                        ])
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('subnet')
                        ->placeholder('192.168.0.0/24')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('gateway')
                        ->placeholder('192.168.0.1')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('dns1')
                        ->label('Primary DNS')
                        ->placeholder('8.8.8.8')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('dns2')
                        ->label('Secondary DNS')
                        ->placeholder('8.8.4.4')
                        ->maxLength(255),
                    \Filament\Forms\Components\Select::make('network_device_id')
                        ->label('Network Device')
                        ->options(NetworkDevice::pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                    \Filament\Forms\Components\Select::make('status')
                        ->options([
                            'active'   => 'Active',
                            'inactive' => 'Inactive',
                        ])
                        ->default('active')
                        ->required(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string|null $state): string => match ($state) {
                        'static'  => 'info',
                        'dhcp'    => 'warning',
                        'hotspot' => 'primary',
                        default   => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('subnet')
                    ->searchable(),
                TextColumn::make('gateway')
                    ->searchable(),
                TextColumn::make('networkDevice.name')
                    ->label('Device')
                    ->searchable(),
                TextColumn::make('ip_addresses_count')
                    ->label('IP Count')
                    ->counts('ipAddresses')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string|null $state): string => match ($state) {
                        'active'   => 'success',
                        'inactive' => 'gray',
                        default    => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'static'  => 'Static',
                        'dhcp'    => 'DHCP',
                        'hotspot' => 'Hotspot',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'active'   => 'Active',
                        'inactive' => 'Inactive',
                    ]),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListIpPools::route('/'),
            'create' => CreateIpPool::route('/create'),
            'edit'   => EditIpPool::route('/{record}/edit'),
        ];
    }
}
