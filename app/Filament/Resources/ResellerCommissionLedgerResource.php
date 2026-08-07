<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResellerCommissionLedgerResource\Pages;
use App\Models\ResellerCommissionLedger;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Read-only ledger — no create, edit, or delete.
 * Every entry is immutable by design (financial audit log).
 */
class ResellerCommissionLedgerResource extends Resource
{
    protected static ?string $model = ResellerCommissionLedger::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Resellers';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Commission Ledger';

    public static function form(Schema $form): Schema
    {
        return $form->components([]); // Read-only
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reseller.name')->label('Reseller')->searchable()->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'earned' => 'success',
                        'withdrawn' => 'info',
                        'adjusted' => 'warning',
                        'reversed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => '৳' . number_format(abs($state) / 100, 2))
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                TextColumn::make('balance_before')
                    ->label('Balance Before')
                    ->formatStateUsing(fn ($state) => '৳' . number_format($state / 100, 2)),
                TextColumn::make('balance_after')
                    ->label('Balance After')
                    ->formatStateUsing(fn ($state) => '৳' . number_format($state / 100, 2)),
                TextColumn::make('description')->limit(50)->tooltip(fn ($state) => $state),
                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    'earned' => 'Earned',
                    'withdrawn' => 'Withdrawn',
                    'adjusted' => 'Adjusted',
                    'reversed' => 'Reversed',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResellerCommissionLedger::route('/'),
        ];
    }
}
