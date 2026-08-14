<?php

namespace App\Filament\Customer\Resources;

use App\Filament\Customer\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'My Invoices';
    protected static string|\UnitEnum|null $navigationGroup = 'Billing';
    protected static ?int $navigationSort = 10;

    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice_number', 'status'];
    }

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
        return 'View your invoices and payment history';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Invoice Details')
                    ->schema([
                        TextInput::make('invoice_number')
                            ->label('Invoice Number')
                            ->readOnly(),
                        DatePicker::make('issue_date')
                            ->label('Issue Date')
                            ->readOnly(),
                        DatePicker::make('due_date')
                            ->label('Due Date')
                            ->readOnly(),
                        SelectColumn::make('status')
                            ->label('Status')
                            ->readOnly(),
                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->readOnly()
                            ->numeric(),
                        TextInput::make('tax_amount')
                            ->label('Tax')
                            ->readOnly()
                            ->numeric(),
                        TextInput::make('total')
                            ->label('Total')
                            ->readOnly()
                            ->numeric(),
                        TextInput::make('amount_paid')
                            ->label('Amount Paid')
                            ->readOnly()
                            ->numeric(),
                        TextInput::make('outstanding_amount')
                            ->label('Outstanding')
                            ->readOnly()
                            ->numeric(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('issue_date')
                    ->label('Issue Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),
                SelectColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('Paid')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('outstanding_amount')
                    ->label('Outstanding')
                    ->money('BDT')
                    ->sortable()
                    ->color(fn ($record) => $record->outstanding_amount > 0 ? 'danger' : 'success'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(\App\Enums\InvoiceStatus::class)
                    ->multiple(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->emptyStateDescription('No invoices found')
            ->emptyStateIcon('heroicon-o-document-text')
            ->defaultSort('issue_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // Relation managers will be added here
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }

    /**
     * Scope the query to the authenticated customer only
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('customer_id', auth('customer')->id());
    }
}
