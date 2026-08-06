<?php

namespace App\Filament\Resources;

use App\Enums\PaymentMethod;
use App\Enums\ReconciliationStatus;
use App\Filament\Resources\PaymentReconciliationResource\Pages;
use App\Models\PaymentReconciliation;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament resource for managing payment reconciliation records.
 * Allows admin staff to view, filter, and resolve payment discrepancies.
 */
class PaymentReconciliationResource extends Resource
{
    protected static ?string $model = PaymentReconciliation::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static \UnitEnum|string|null $navigationGroup = 'Billing & Payments';
    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return 'Payment Reconciliation';
    }

    public static function getPluralLabel(): string
    {
        return 'Payment Reconciliations';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Reconciliation Details')
                    ->schema([
                        TextInput::make('gateway')
                            ->label('Gateway')
                            ->required()
                            ->disabled(),

                        TextInput::make('gateway_reference')
                            ->label('Gateway Reference')
                            ->maxLength(255)
                            ->disabled(),

                        TextInput::make('recorded_amount')
                            ->label('Recorded Amount (poysha)')
                            ->numeric()
                            ->disabled(),

                        TextInput::make('settlement_amount')
                            ->label('Settlement Amount (poysha)')
                            ->numeric()
                            ->disabled(),

                        DateTimePicker::make('settlement_date')
                            ->label('Settlement Date')
                            ->disabled(),

                        Select::make('status')
                            ->label('Status')
                            ->options(ReconciliationStatus::options())
                            ->required(),
                    ])->columns(2),

                Section::make('Related Payment')
                    ->schema([
                        TextInput::make('payment_id')
                            ->label('Payment ID')
                            ->numeric()
                            ->disabled(),
                    ])->columns(1),

                Section::make('Resolution')
                    ->schema([
                        Select::make('resolved_by')
                            ->label('Resolved By')
                            ->relationship('resolvedBy', 'name')
                            ->searchable()
                            ->nullable(),

                        DateTimePicker::make('resolved_at')
                            ->label('Resolved At')
                            ->nullable(),

                        Textarea::make('resolution_notes')
                            ->label('Resolution Notes')
                            ->rows(3)
                            ->nullable(),
                    ])->columns(2),

                Section::make('Additional Information')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->nullable(),

                        Textarea::make('settlement_data')
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
                TextColumn::make('gateway')
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

                TextColumn::make('gateway_reference')
                    ->label('Gateway Ref')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('payment_id')
                    ->label('Payment ID')
                    ->numeric()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('recorded_amount')
                    ->label('Recorded (poysha)')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('settlement_amount')
                    ->label('Settlement (poysha)')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('settlement_date')
                    ->label('Settlement Date')
                    ->dateTime()
                    ->sortable(),

                SelectColumn::make('status')
                    ->label('Status')
                    ->options(ReconciliationStatus::options())
                    ->searchable()
                    ->sortable(),

                TextColumn::make('resolved_at')
                    ->label('Resolved At')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gateway')
                    ->label('Gateway')
                    ->options(PaymentMethod::options()),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ReconciliationStatus::options()),

                Filter::make('unresolved')
                    ->label('Unresolved Only')
                    ->query(fn (Builder $query) => $query->whereIn('status', [
                        ReconciliationStatus::PENDING->value,
                        ReconciliationStatus::DISCREPANCY->value
                    ])),

                Filter::make('discrepancies')
                    ->label('Discrepancies Only')
                    ->query(fn (Builder $query) => $query->where('status', ReconciliationStatus::DISCREPANCY->value)),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
