<?php

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
use Filament\Tables\Filters\DateRangeFilter;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Payments';
    protected static \UnitEnum|string|null $navigationGroup = 'Billing & Payments';
    protected static ?int $navigationSort = 20;

    public static function getPluralLabel(): string
    {
        return 'Payments';
    }

    public static function getSingularLabel(): string
    {
        return 'Payment';
    }

    public static function getDescription(): string
    {
        return 'Track customer payments and payment methods';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Payment Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('customer_id')
                                    ->label('Customer')
                                    ->required()
                                    ->relationship('customer', 'full_name')
                                    ->searchable()
                                    ->preload(),
                                Select::make('invoice_id')
                                    ->label('Invoice')
                                    ->required()
                                    ->relationship('invoice', 'invoice_number')
                                    ->searchable()
                                    ->preload(),
                                Select::make('method')
                                    ->label('Payment Method')
                                    ->required()
                                    ->options(PaymentMethod::class)
                                    ->default(PaymentMethod::CASH),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Amount (poysha)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Amount in poysha (BDT \u00d7 100)'),
                                TextInput::make('fee_amount')
                                    ->label('Fee (poysha)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                TextInput::make('net_amount')
                                    ->label('Net Amount (poysha)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0),
                            ]),
                    ]),

                Section::make('Status & Gateway')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->required()
                                    ->options(PaymentStatus::class)
                                    ->default(PaymentStatus::PENDING),
                                DateTimePicker::make('paid_at')
                                    ->label('Paid At')
                                    ->nullable(),
                                Select::make('collected_by')
                                    ->label('Collected By')
                                    ->relationship('collectedBy', 'name')
                                    ->nullable()
                                    ->searchable()
                                    ->preload(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('gateway_reference')
                                    ->label('Gateway Reference')
                                    ->nullable()
                                    ->maxLength(255),
                                TextInput::make('gateway_response')
                                    ->label('Gateway Response')
                                    ->nullable()
                                    ->maxLength(500),
                            ]),
                    ]),

                Section::make('Reseller & Notes')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('reseller_id')
                                    ->label('Reseller')
                                    ->relationship('reseller', 'name')
                                    ->nullable()
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('receipt_path')
                                    ->label('Receipt Path')
                                    ->nullable()
                                    ->maxLength(255),
                            ]),
                        TextInput::make('notes')
                            ->label('Notes')
                            ->nullable()
                            ->maxLength(500),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Amount (BDT)')
                    ->state(fn ($record) => number_format($record->amount / 100, 2))
                    ->sortable(),
                TextColumn::make('fee_amount')
                    ->label('Fee (BDT)')
                    ->state(fn ($record) => number_format($record->fee_amount / 100, 2))
                    ->sortable(),
                TextColumn::make('net_amount')
                    ->label('Net (BDT)')
                    ->state(fn ($record) => number_format($record->net_amount / 100, 2))
                    ->sortable(),
                SelectColumn::make('method')
                    ->label('Method')
                    ->options(PaymentMethod::class)
                    ->sortable(),
                SelectColumn::make('status')
                    ->label('Status')
                    ->options(PaymentStatus::class)
                    ->sortable(),
                TextColumn::make('gateway_reference')
                    ->label('Gateway Ref')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->label('Payment Method')
                    ->options(PaymentMethod::class)
                    ->multiple(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(PaymentStatus::class)
                    ->multiple(),
                DateRangeFilter::make('paid_at')
                    ->label('Paid Date Range'),
                DateRangeFilter::make('created_at')
                    ->label('Created Date Range'),
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
            ->emptyStateDescription('No payments found')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->defaultSort('paid_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            'customer' => self::getCustomerRelation(),
            'invoice' => self::getInvoiceRelation(),
        ];
    }

    protected static function getCustomerRelation(): \Filament\Resources\RelationManagers\RelationManager
    {
        return \Filament\Resources\RelationManagers\RelationManager::make('customer');
    }

    protected static function getInvoiceRelation(): \Filament\Resources\RelationManagers\RelationManager
    {
        return \Filament\Resources\RelationManagers\RelationManager::make('invoice');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'view' => Pages\ViewPayment::route('/{record}'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
