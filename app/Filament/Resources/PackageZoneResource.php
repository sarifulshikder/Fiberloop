<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PackageZoneResource\Pages;
use App\Models\PackageZone;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class PackageZoneResource extends Resource
{
    protected static ?string $model = PackageZone::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationLabel = 'Package Zones';
    protected static \UnitEnum|string|null $navigationGroup = 'Products & Pricing';
    protected static ?int $navigationSort = 40;

    public static function getPluralLabel(): string
    {
        return 'Package Zones';
    }

    public static function getSingularLabel(): string
    {
        return 'Package Zone';
    }

    public static function getDescription(): string
    {
        return 'Manage package availability by zone and area';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Package & Location')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('package_id')
                                    ->label('Package')
                                    ->required()
                                    ->relationship('package', 'name'),
                                TextInput::make('zone')
                                    ->label('Zone')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('area')
                                    ->label('Area')
                                    ->required()
                                    ->maxLength(100),
                            ]),
                    ]),

                Section::make('Availability & Pricing')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Toggle::make('is_available')
                                    ->label('Available')
                                    ->default(true),
                                TextInput::make('custom_price')
                                    ->label('Custom Price (BDT × 100)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->nullable()
                                    ->helperText('Leave empty to use package default price'),
                            ]),
                    ]),

                Section::make('Capacity Constraints')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('max_connections')
                                    ->label('Maximum Connections')
                                    ->numeric()
                                    ->minValue(0)
                                    ->nullable()
                                    ->helperText('Leave empty for unlimited'),
                                TextInput::make('current_connections')
                                    ->label('Current Connections')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                            ]),
                    ]),

                Section::make('Settings')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('tenant_id')
                                    ->label('Tenant')
                                    ->relationship('tenant', 'name')
                                    ->nullable(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('package.name')
                    ->label('Package')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('zone')
                    ->label('Zone')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('area')
                    ->label('Area')
                    ->searchable()
                    ->sortable(),
                BooleanColumn::make('is_available')
                    ->label('Available')
                    ->sortable(),
                TextColumn::make('custom_price')
                    ->label('Custom Price')
                    ->state(fn ($record) => $record->custom_price ? 'BDT ' . number_format($record->custom_price / 100, 2) : 'Default')
                    ->sortable(),
                TextColumn::make('current_connections')
                    ->label('Connections')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_connections')
                    ->label('Max Connections')
                    ->numeric()
                    ->placeholder('Unlimited')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('package_id')
                    ->label('Package')
                    ->relationship('package', 'name')
                    ->multiple(),
                TernaryFilter::make('is_available')
                    ->label('Availability Status')
                    ->nullable(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ])
            ->emptyStateDescription('No package zone assignments found')
            ->emptyStateIcon('heroicon-o-map-pin')
            ->defaultSort('package_id', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            // Relation managers can be added here
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPackageZones::route('/'),
            'create' => Pages\CreatePackageZone::route('/create'),
            'view' => Pages\ViewPackageZone::route('/{record}'),
            'edit' => Pages\EditPackageZone::route('/{record}/edit'),
        ];
    }
}