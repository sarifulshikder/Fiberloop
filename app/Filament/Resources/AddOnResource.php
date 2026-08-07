<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AddOnResource\Pages;
use App\Models\AddOn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AddOnResource extends Resource
{
    protected static ?string $model = AddOn::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?string $navigationLabel = 'Add-Ons';
    protected static string|\UnitEnum|null $navigationGroup = 'Products & Pricing';
    protected static ?int $navigationSort = 30;

    public static function getPluralLabel(): string
    {
        return 'Add-Ons';
    }

    public static function getSingularLabel(): string
    {
        return 'Add-On';
    }

    public static function getDescription(): string
    {
        return 'Manage additional services and add-ons for packages';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Add-On Name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(column: 'code', ignorable: fn ($record) => $record),
                                Select::make('type')
                                    ->label('Type')
                                    ->required()
                                    ->options(AddOn::getTypes())
                                    ->default('other'),
                            ]),
                        TextInput::make('description')
                            ->label('Description')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),

                Section::make('Pricing')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('price')
                                    ->label('Price (BDT × 100)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Store as integer in poysha (BDT × 100)'),
                                Select::make('billing_cycle')
                                    ->label('Billing Cycle')
                                    ->required()
                                    ->options(AddOn::getBillingCycles())
                                    ->default('monthly'),
                            ]),
                    ]),

                Section::make('Configuration')
                    ->schema([
                        TextInput::make('configuration')
                            ->label('Configuration JSON')
                            ->maxLength(500)
                            ->helperText('Optional JSON configuration for add-on specific settings'),
                    ]),

                Section::make('Status & Display')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->default(0),
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
                TextColumn::make('name')
                    ->label('Add-On Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                SelectColumn::make('type')
                    ->label('Type')
                    ->options(AddOn::getTypes())
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Price')
                    ->state(fn ($record) => 'BDT ' . number_format($record->price / 100, 2))
                    ->sortable(),
                SelectColumn::make('billing_cycle')
                    ->label('Billing Cycle')
                    ->options(AddOn::getBillingCycles())
                    ->sortable(),
                BooleanColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(AddOn::getTypes())
                    ->multiple(),
                SelectFilter::make('billing_cycle')
                    ->label('Billing Cycle')
                    ->options(AddOn::getBillingCycles())
                    ->multiple(),
                TernaryFilter::make('is_active')
                    ->label('Active Status')
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
            ->emptyStateDescription('No add-ons found')
            ->emptyStateIcon('heroicon-o-plus-circle')
            ->defaultSort('sort_order', 'asc');
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
            'index' => Pages\ListAddOns::route('/'),
            'create' => Pages\CreateAddOn::route('/create'),
            'view' => Pages\ViewAddOn::route('/{record}'),
            'edit' => Pages\EditAddOn::route('/{record}/edit'),
        ];
    }
}
