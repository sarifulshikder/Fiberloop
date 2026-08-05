<?php

namespace App\Filament\Resources;

use App\Enums\BillingType;
use App\Enums\PackageBillingCycle;
use App\Filament\Resources\PackageResource\Pages;
use App\Models\Package;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Packages';
    protected static \UnitEnum|string|null $navigationGroup = 'Products & Pricing';
    protected static ?int $navigationSort = 10;

    public static function getPluralLabel(): string
    {
        return 'Packages';
    }

    public static function getSingularLabel(): string
    {
        return 'Package';
    }

    public static function getDescription(): string
    {
        return 'Manage internet packages, pricing, speeds, and FUP settings';
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
                                    ->label('Package Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                TextInput::make('code')
                                    ->label('Package Code')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(column: 'code', ignore: fn ($record) => $record),
                            ]),
                        TextInput::make('description')
                            ->label('Description')
                            ->maxLength(500),
                        MarkdownEditor::make('features')
                            ->label('Features')
                            ->columnSpanFull(),
                    ]),

                Section::make('Speed & FUP Settings')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('download_speed')
                                    ->label('Download Speed (Mbps)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1),
                                TextInput::make('upload_speed')
                                    ->label('Upload Speed (Mbps)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1),
                                TextInput::make('fup_threshold')
                                    ->label('FUP Threshold (GB)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('0 = No FUP limit'),
                                Select::make('fup_reset_cycle')
                                    ->label('FUP Reset Cycle')
                                    ->options([
                                        'monthly' => 'Monthly',
                                        'daily' => 'Daily',
                                        'custom' => 'Custom',
                                    ])
                                    ->default('monthly'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('fup_throttled_download')
                                    ->label('Throttled Download (Mbps)')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('fup_throttled_upload')
                                    ->label('Throttled Upload (Mbps)')
                                    ->numeric()
                                    ->minValue(0),
                            ]),
                    ]),

                Section::make('Pricing')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('price')
                                    ->label('Monthly Price (BDT × 100)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Store as integer in poysha (BDT × 100)'),
                                Select::make('billing_cycle')
                                    ->label('Billing Cycle')
                                    ->required()
                                    ->options(PackageBillingCycle::class)
                                    ->default(PackageBillingCycle::MONTHLY),
                                Select::make('billing_type')
                                    ->label('Billing Type')
                                    ->required()
                                    ->options(BillingType::class)
                                    ->default(BillingType::PREPAID),
                                TextInput::make('tax_rate')
                                    ->label('Tax Rate (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(15),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('installation_fee')
                                    ->label('Installation Fee (BDT × 100)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                TextInput::make('security_deposit')
                                    ->label('Security Deposit (BDT × 100)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                            ]),
                    ]),

                Section::make('Status & Display')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                Toggle::make('is_popular')
                                    ->label('Popular Package')
                                    ->default(false),
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
                    ->label('Package Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('download_speed')
                    ->label('Download (Mbps)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('upload_speed')
                    ->label('Upload (Mbps)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Price')
                    ->state(fn ($record) => 'BDT ' . number_format($record->price / 100, 2))
                    ->sortable(),
                SelectColumn::make('billing_cycle')
                    ->label('Billing Cycle')
                    ->options(PackageBillingCycle::class)
                    ->sortable(),
                SelectColumn::make('billing_type')
                    ->label('Billing Type')
                    ->options(BillingType::class)
                    ->sortable(),
                TextColumn::make('fup_threshold')
                    ->label('FUP (GB)')
                    ->numeric()
                    ->placeholder('No limit'),
                BooleanColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),
                BooleanColumn::make('is_popular')
                    ->label('Popular')
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
                SelectFilter::make('billing_cycle')
                    ->label('Billing Cycle')
                    ->options(PackageBillingCycle::class)
                    ->multiple(),
                SelectFilter::make('billing_type')
                    ->label('Billing Type')
                    ->options(BillingType::class)
                    ->multiple(),
                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->nullable(),
                TernaryFilter::make('is_popular')
                    ->label('Popular Status')
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
            ->emptyStateDescription('No packages found')
            ->emptyStateIcon('heroicon-o-cube')
            ->defaultSort('sort_order', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPackages::route('/'),
            'create' => Pages\CreatePackage::route('/create'),
            'view' => Pages\ViewPackage::route('/{record}'),
            'edit' => Pages\EditPackage::route('/{record}/edit'),
        ];
    }
}