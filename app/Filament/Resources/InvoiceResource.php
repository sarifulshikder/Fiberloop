<?php

namespace App\Filament\Resources;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
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

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Invoices';
    protected static \UnitEnum|string|null $navigationGroup = 'Billing & Payments';
    protected static ?int $navigationSort = 10;

    public static function getPluralLabel(): string
    {
        return 'Invoices';
    }

    public static function getSingularLabel(): string
    {
        return 'Invoice';
    }

    public static function getDescription(): string
    {
        return 'Manage customer invoices, billing cycles, and payment tracking';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Invoice Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('invoice_number')
                                    ->label('Invoice Number')
                                    ->required()
                                    ->maxLength(50)
                                    ->readOnly(),
                                Select::make('status')
                                    ->label('Status')
                                    ->required()
                                    ->options(InvoiceStatus::class)
                                    ->default(InvoiceStatus::DRAFT),
                                Select::make('billing_type')
                                    ->label('Billing Type')
                                    ->options([
                                        'postpaid' => 'Postpaid',
                                        'prepaid' => 'Prepaid',
                                    ])
                                    ->nullable(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                DatePicker::make('period_start')
                                    ->label('Period Start')
                                    ->required(),
                                DatePicker::make('period_end')
                                    ->label('Period End')
                                    ->required(),
                                DatePicker::make('due_date')
                                    ->label('Due Date')
                                    ->required(),
                            ]),
                    ]),

                Section::make('Customer & Subscription')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('customer_id')
                                    ->label('Customer')
                                    ->required()
                                    ->options(function () {
                                        return \App\Models\Customer::all()->pluck('full_name', 'id');
                                    })
                                    ->searchable()
                                    ->preload(),
                                Select::make('subscription_id')
                                    ->label('Subscription')
                                    ->required()
                                    ->relationship('subscription', 'uuid')
                                    ->searchable()
                                    ->preload(),
                                Select::make('reseller_id')
                                    ->label('Reseller')
                                    ->relationship('reseller', 'name')
                                    ->nullable()
                                    ->searchable()
                                    ->preload(),
                            ]),
                    ]),

                Section::make('Amounts')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Subtotal (poysha)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Amount in poysha (BDT × 100)'),
                                TextInput::make('tax_amount')
                                    ->label('Tax Amount (poysha)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Amount in poysha (BDT × 100)'),
                                TextInput::make('tax_rate')
                                    ->label('Tax Rate (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100),
                                TextInput::make('discount_amount')
                                    ->label('Discount (poysha)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('total')
                                    ->label('Total (poysha)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Amount in poysha (BDT × 100)'),
                                TextInput::make('paid_amount')
                                    ->label('Paid Amount (poysha)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                                TextInput::make('outstanding_amount')
                                    ->label('Outstanding (poysha)')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Amount in poysha (BDT × 100)'),
                            ]),
                    ]),

                Section::make('Proration')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('is_prorated')
                                    ->label('Is Prorated')
                                    ->options([
                                        true => 'Yes',
                                        false => 'No',
                                    ])
                                    ->default(false),
                                TextInput::make('proration_amount')
                                    ->label('Proration Amount (poysha)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0),
                            ]),
                        MarkdownEditor::make('notes')
                            ->label('Notes')
                            ->nullable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Files')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('pdf_path')
                                    ->label('PDF Path')
                                    ->nullable()
                                    ->maxLength(255),
                                BooleanColumn::make('pdf_generated')
                                    ->label('PDF Generated'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subscription.package.name')
                    ->label('Package')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period_start')
                    ->label('Period Start')
                    ->date()
                    ->sortable(),
                TextColumn::make('period_end')
                    ->label('Period End')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total (BDT)')
                    ->state(fn ($record) => number_format($record->total / 100, 2))
                    ->sortable(),
                TextColumn::make('paid_amount')
                    ->label('Paid (BDT)')
                    ->state(fn ($record) => number_format($record->paid_amount / 100, 2))
                    ->sortable(),
                TextColumn::make('outstanding_amount')
                    ->label('Outstanding (BDT)')
                    ->state(fn ($record) => number_format($record->outstanding_amount / 100, 2))
                    ->sortable(),
                SelectColumn::make('status')
                    ->label('Status')
                    ->options(InvoiceStatus::class)
                    ->sortable(),
                BooleanColumn::make('is_prorated')
                    ->label('Prorated')
                    ->sortable(),
                TextColumn::make('billing_type')
                    ->label('Billing Type')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(InvoiceStatus::class)
                    ->multiple(),
                SelectFilter::make('billing_type')
                    ->label('Billing Type')
                    ->options([
                        'postpaid' => 'Postpaid',
                        'prepaid' => 'Prepaid',
                    ])
                    ->multiple(),
                TernaryFilter::make('is_prorated')
                    ->label('Prorated')
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
            ->emptyStateDescription('No invoices found')
            ->emptyStateIcon('heroicon-o-document-text')
            ->defaultSort('invoice_number', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            'customer',
            'subscription',
            'items',
            'payments',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
