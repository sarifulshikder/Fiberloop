<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromoCodeResource\Pages;
use App\Models\PromoCode;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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

class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationLabel = 'Promo Codes';
    protected static \UnitEnum|string|null $navigationGroup = 'Products & Pricing';
    protected static ?int $navigationSort = 20;

    public static function getPluralLabel(): string
    {
        return 'Promo Codes';
    }

    public static function getSingularLabel(): string
    {
        return 'Promo Code';
    }

    public static function getDescription(): string
    {
        return 'Manage promotional codes, discounts, and vouchers';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Promo Code')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(column: 'code', ignore: fn ($record) => $record),
                                TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(255),
                            ]),
                        TextInput::make('description')
                            ->label('Description')
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),

                Section::make('Discount Configuration')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('discount_type')
                                    ->label('Discount Type')
                                    ->required()
                                    ->options([
                                        'percentage' => 'Percentage',
                                        'fixed_amount' => 'Fixed Amount',
                                        'fixed_price' => 'Fixed Price',
                                    ])
                                    ->default('percentage'),
                                TextInput::make('discount_value')
                                    ->label('Discount Value')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('For percentage: 1-100. For fixed amounts: in poysha (BDT × 100)'),
                            ]),
                        Select::make('applies_to')
                            ->label('Applies To')
                            ->required()
                            ->options([
                                'all_packages' => 'All Packages',
                                'specific_packages' => 'Specific Packages',
                                'minimum_amount' => 'Minimum Amount',
                            ])
                            ->default('all_packages'),
                    ]),

                Section::make('Time Constraints')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                DateTimePicker::make('start_date')
                                    ->label('Start Date')
                                    ->nullable()
                                    ->helperText('Leave empty for immediate activation'),
                                DateTimePicker::make('end_date')
                                    ->label('End Date')
                                    ->nullable()
                                    ->helperText('Leave empty for no expiration'),
                            ]),
                    ]),

                Section::make('Usage Limits')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('max_uses')
                                    ->label('Maximum Uses')
                                    ->numeric()
                                    ->minValue(0)
                                    ->nullable()
                                    ->helperText('Leave empty for unlimited'),
                                TextInput::make('uses_count')
                                    ->label('Current Uses')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                TextInput::make('max_uses_per_customer')
                                    ->label('Max Uses Per Customer')
                                    ->numeric()
                                    ->minValue(0)
                                    ->nullable()
                                    ->helperText('Leave empty for unlimited'),
                            ]),
                    ]),

                Section::make('Status & Settings')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
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
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                SelectColumn::make('discount_type')
                    ->label('Discount Type')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed_amount' => 'Fixed Amount',
                        'fixed_price' => 'Fixed Price',
                    ])
                    ->sortable(),
                TextColumn::make('discount_value')
                    ->label('Discount Value')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('uses_count')
                    ->label('Uses')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_uses')
                    ->label('Max Uses')
                    ->numeric()
                    ->placeholder('Unlimited'),
                BooleanColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('end_date')
                    ->label('End Date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('discount_type')
                    ->label('Discount Type')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed_amount' => 'Fixed Amount',
                        'fixed_price' => 'Fixed Price',
                    ])
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
            ->emptyStateDescription('No promo codes found')
            ->emptyStateIcon('heroicon-o-gift')
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // Relation managers can be added here for packages
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromoCodes::route('/'),
            'create' => Pages\CreatePromoCode::route('/create'),
            'view' => Pages\ViewPromoCode::route('/{record}'),
            'edit' => Pages\EditPromoCode::route('/{record}/edit'),
        ];
    }
}