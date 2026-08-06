<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IncidentResource\Pages\CreateIncident;
use App\Filament\Resources\IncidentResource\Pages\EditIncident;
use App\Filament\Resources\IncidentResource\Pages\ListIncidents;
use App\Models\Incident;
use App\Models\NetworkDevice;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class IncidentResource extends Resource
{
    protected static ?string $model = Incident::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static \UnitEnum|string|null $navigationGroup = 'Network';
    protected static ?string $navigationLabel = 'Incidents / Outages';
    protected static ?int $navigationSort = 5;
    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Incident Details')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                    \Filament\Forms\Components\Textarea::make('description')
                        ->rows(4),
                    \Filament\Forms\Components\Select::make('severity')
                        ->options([
                            'critical' => 'Critical',
                            'warning'  => 'Warning',
                            'info'     => 'Info',
                        ])
                        ->default('warning')
                        ->required(),
                    \Filament\Forms\Components\Select::make('status')
                        ->options([
                            'open'     => 'Open',
                            'resolved' => 'Resolved',
                        ])
                        ->default('open')
                        ->required(),
                    \Filament\Forms\Components\Select::make('network_device_id')
                        ->label('Network Device')
                        ->options(NetworkDevice::pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                    \Filament\Forms\Components\TextInput::make('area_zone')
                        ->maxLength(255),
                    \Filament\Forms\Components\DateTimePicker::make('started_at'),
                    \Filament\Forms\Components\DateTimePicker::make('resolved_at'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string|null $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning'  => 'warning',
                        'info'     => 'info',
                        default    => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string|null $state): string => match ($state) {
                        'open'     => 'danger',
                        'resolved' => 'success',
                        default    => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('networkDevice.name')
                    ->label('Device')
                    ->searchable(),
                TextColumn::make('area_zone')
                    ->label('Area/Zone')
                    ->searchable(),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('resolved_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open'     => 'Open',
                        'resolved' => 'Resolved',
                    ]),
                SelectFilter::make('severity')
                    ->options([
                        'critical' => 'Critical',
                        'warning'  => 'Warning',
                        'info'     => 'Info',
                    ]),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('resolve')
                        ->label('Mark as Resolved')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update([
                            'status'      => 'resolved',
                            'resolved_at' => now(),
                        ]))
                        ->requiresConfirmation(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListIncidents::route('/'),
            'create' => CreateIncident::route('/create'),
            'edit'   => EditIncident::route('/{record}/edit'),
        ];
    }
}
