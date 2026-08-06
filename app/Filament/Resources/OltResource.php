<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OltResource\Pages\CreateOlt;
use App\Filament\Resources\OltResource\Pages\EditOlt;
use App\Filament\Resources\OltResource\Pages\ListOlts;
use App\Models\NetworkDevice;
use App\Models\Olt;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OltResource extends Resource
{
    protected static ?string $model = Olt::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cpu-chip';
    protected static \UnitEnum|string|null $navigationGroup = 'Network';
    protected static ?string $navigationLabel = 'OLTs';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('OLT Details')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    \Filament\Forms\Components\Select::make('network_device_id')
                        ->label('Network Device')
                        ->options(NetworkDevice::pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('chassis_id')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('firmware_version')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('hardware_version')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('total_pon_ports')
                        ->numeric()
                        ->default(0),
                    \Filament\Forms\Components\TextInput::make('max_onus_per_pon')
                        ->numeric()
                        ->default(64),
                    \Filament\Forms\Components\TextInput::make('rack')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('slot')
                        ->maxLength(255),
                    \Filament\Forms\Components\Textarea::make('location_notes')
                        ->rows(2),
                    \Filament\Forms\Components\Toggle::make('is_active')
                        ->default(true),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->rows(3),
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
                TextColumn::make('networkDevice.name')
                    ->label('Network Device')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_pon_ports')
                    ->label('PON Ports')
                    ->sortable(),
                TextColumn::make('used_pon_ports')
                    ->label('Used Ports')
                    ->sortable(),
                TextColumn::make('firmware_version')
                    ->label('Firmware')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('last_sync_at')
                    ->label('Last Sync')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
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
            'index'  => ListOlts::route('/'),
            'create' => CreateOlt::route('/create'),
            'edit'   => EditOlt::route('/{record}/edit'),
        ];
    }
}
