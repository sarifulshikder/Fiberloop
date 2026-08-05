<?php

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Enums\ReconciliationStatus;
use App\Filament\Resources\PaymentReconciliationResource\Pages;
use App\Models\PaymentReconciliation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament resource for managing payment reconciliation records.
 * Allows admin staff to view, filter, and resolve payment discrepancies.
 */
class PaymentReconciliationResource extends Resource
{
    protected static ?string $model = PaymentReconciliation::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Billing';
    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return 'Payment Reconciliation';
    }

    public static function getPluralLabel(): string
    {
        return 'Payment Reconciliations';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Reconciliation Details')
                    ->schema([
                        Forms\Components\TextInput::make('gateway')
                            ->label('Gateway')
                            ->options(PaymentMethod::options())
                            ->required()
                            ->disabled(),

                        Forms\Components\TextInput::make('gateway_reference')
                            ->label('Gateway Reference')
                            ->maxLength(255)
                            ->disabled(),

                        Forms\Components\TextInput::make('recorded_amount')
                            ->label('Recorded Amount (poysha)')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('settlement_amount')
                            ->label('Settlement Amount (poysha)')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('settlement_date')
                            ->label('Settlement Date')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(ReconciliationStatus::options())
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Related Payment')
                    ->schema([
                        Forms\Components\TextInput::make('payment_id')
                            ->label('Payment ID')
                            ->numeric()
                            ->disabled(),
                    ])->columns(1),

                Forms\Components\Section::make('Resolution')
                    ->schema([
                        Forms\Components\Select::make('resolved_by')
                            ->label('Resolved By')
                            ->relationship('resolvedBy', 'name')
                            ->searchable()
                            ->nullable(),

                        Forms\Components\DateTimePicker::make('resolved_at')
                            ->label('Resolved At')
                            ->nullable(),

                        Forms\Components\Textarea::make('resolution_notes')
                            ->label('Resolution Notes')
                            ->rows(3)
                            ->nullable(),
                    ])->columns(2),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->nullable(),

                        Forms\Components\Textarea::make('settlement_data')
                            ->label('Settlement Data (JSON)')
                            ->rows(5)
                            ->disabled(),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('gateway')
                    ->label('Gateway')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        PaymentMethod::BKASH->value => 'success',
                        PaymentMethod::NAGAD->value => 'info',
                        PaymentMethod::SSLCOMMERZ->value => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('gateway_reference')
                    ->label('Gateway Ref')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_id')
                    ->label('Payment ID')
                    ->numeric()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('recorded_amount')
                    ->label('Recorded (poysha)')
                    ->numeric()
                    ->money(fn ($amount) => $amount / 100, 'BDT', true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('settlement_amount')
                    ->label('Settlement (poysha)')
                    ->numeric()
                    ->money(fn ($amount) => $amount / 100, 'BDT', true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('settlement_date')
                    ->label('Settlement Date')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options(ReconciliationStatus::options())
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        ReconciliationStatus::MATCHED->value => 'success',
                        ReconciliationStatus::PENDING->value => 'warning',
                        ReconciliationStatus::DISCREPANCY->value => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('Resolved At')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gateway')
                    ->label('Gateway')
                    ->options(PaymentMethod::options()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(ReconciliationStatus::options()),

                Tables\Filters\Filter::make('unresolved')
                    ->label('Unresolved Only')
                    ->query(fn (Builder $query) => $query->whereIn('status', [
                        ReconciliationStatus::PENDING->value,
                        ReconciliationStatus::DISCREPANCY->value
                    ])),

                Tables\Filters\Filter::make('discrepancies')
                    ->label('Discrepancies Only')
                    ->query(fn (Builder $query) => $query->where('status', ReconciliationStatus::DISCREPANCY->value)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentReconciliations::route('/'),
            'create' => Pages\CreatePaymentReconciliation::route('/create'),
            'view' => Pages\ViewPaymentReconciliation::route('/{record}'),
            'edit' => Pages\EditPaymentReconciliation::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereIn('status', [
            ReconciliationStatus::PENDING->value,
            ReconciliationStatus::DISCREPANCY->value
        ])->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }
}
