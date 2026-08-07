<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OnuResource\Pages\CreateOnu;
use App\Filament\Resources\OnuResource\Pages\EditOnu;
use App\Filament\Resources\OnuResource\Pages\ListOnus;
use App\Models\Customer;
use App\Models\Olt;
use App\Models\Onu;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OnuResource extends Resource
{
    protected static ?string $model = Onu::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wifi';
    protected static string|\UnitEnum|null $navigationGroup = 'Network';
    protected static ?string $navigationLabel = 'ONUs';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'serial_number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('ONU Details')
                ->schema([
                    \Filament\Forms\Components\Select::make('olt_id')
                        ->label('OLT')
                        ->options(Olt::pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    \Filament\Forms\Components\Select::make('customer_id')
                        ->label('Customer')
                        ->relationship(
                            name: 'customer',
                            modifyQueryUsing: fn (Builder $query) => $query->orderBy('first_name')->orderBy('last_name'),
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (Customer $record) => "{$record->first_name} {$record->last_name}"
                        )
                        ->searchable(['first_name', 'last_name', 'phone'])
                        ->nullable(),
                    \Filament\Forms\Components\TextInput::make('serial_number')
                        ->required()
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('mac_address')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('pon_port')
                        ->numeric(),
                    \Filament\Forms\Components\TextInput::make('pon_port_name')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('ONU_id')
                        ->label('ONU ID')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('ONU_type')
                        ->label('ONU Type')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('vendor_id')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('firmware_version')
                        ->maxLength(255),
                    \Filament\Forms\Components\Select::make('operational_state')
                        ->options([
                            'online'  => 'Online',
                            'offline' => 'Offline',
                            'degraded' => 'Degraded',
                        ]),
                    \Filament\Forms\Components\Toggle::make('is_active')
                        ->default(true),
                    \Filament\Forms\Components\Toggle::make('is_registered')
                        ->default(false),
                    \Filament\Forms\Components\DateTimePicker::make('registered_at'),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->rows(3),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('serial_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('mac_address')
                    ->searchable(),
                TextColumn::make('olt.name')
                    ->label('OLT')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pon_port_name')
                    ->label('PON Port')
                    ->searchable(),
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->customer?->full_name ?? '—'),
                TextColumn::make('optical_signal_db')
                    ->label('Rx Power (dBm)')
                    ->numeric(2)
                    ->sortable()
                    ->color(fn ($state) => $state === null ? null : ($state < -27 ? 'danger' : ($state < -24 ? 'warning' : 'success'))),
                TextColumn::make('operational_state')
                    ->label('State')
                    ->badge()
                    ->color(fn (string|null $state): string => match ($state) {
                        'online'   => 'success',
                        'offline'  => 'danger',
                        'degraded' => 'warning',
                        default    => 'gray',
                    })
                    ->sortable(),
                IconColumn::make('is_registered')
                    ->label('Registered')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('last_signal_check_at')
                    ->label('Last Check')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('operational_state')
                    ->options([
                        'online'   => 'Online',
                        'offline'  => 'Offline',
                        'degraded' => 'Degraded',
                    ]),
                SelectFilter::make('is_registered')
                    ->label('Registration')
                    ->options([
                        '1' => 'Registered',
                        '0' => 'Unregistered',
                    ]),
                SelectFilter::make('olt_id')
                    ->label('OLT')
                    ->options(Olt::pluck('name', 'id'))
                    ->searchable(),
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
            'index'  => ListOnus::route('/'),
            'create' => CreateOnu::route('/create'),
            'edit'   => EditOnu::route('/{record}/edit'),
        ];
    }
}
