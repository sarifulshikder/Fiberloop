<?php

namespace App\Filament\Customer\Resources;

use App\Filament\Customer\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Payment History';
    protected static string|\UnitEnum|null $navigationGroup = 'Billing';
    protected static ?int $navigationSort = 20;

    public static function getGloballySearchableAttributes(): array
    {
        return ['gateway_reference', 'method'];
    }

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
        return 'View your payment history';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Payment Details')
                    ->schema([
                        TextInput::make('invoice_id')
                            ->label('Invoice #')
                            ->readOnly(),
                        TextInput::make('amount')
                            ->label('Amount')
                            ->readOnly()
                            ->numeric(),
                        Select::make('method')
                            ->label('Payment Method')
                            ->options(\App\Enums\PaymentMethod::class)
                            ->readOnly(),
                        TextInput::make('gateway_reference')
                            ->label('Reference')
                            ->readOnly(),
                        SelectColumn::make('status')
                            ->label('Status')
                            ->readOnly(),
                        DatePicker::make('paid_at')
                            ->label('Payment Date')
                            ->readOnly(),
                        TextInput::make('collected_by')
                            ->label('Collected By')
                            ->readOnly(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice #')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('BDT')
                    ->sortable(),
                SelectColumn::make('method')
                    ->label('Method')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('gateway_reference')
                    ->label('Reference')
                    ->sortable()
                    ->searchable(),
                SelectColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('paid_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('collected_by')
                    ->label('Collected By')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->label('Payment Method')
                    ->options(\App\Enums\PaymentMethod::class)
                    ->multiple(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(\App\Enums\PaymentStatus::class)
                    ->multiple(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->emptyStateDescription('No payments found')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->defaultSort('paid_at', 'desc');
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
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }

    /**
     * Scope the query to the authenticated customer only
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('invoice', fn ($query) => $query->where('customer_id', auth('customer')->id()));
    }
}
