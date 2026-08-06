<?php

namespace App\Filament\Pages;

use App\Models\RadAcct;
use App\Services\Radius\RadiusSessionService;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LiveRadiusSessions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-signal';
    protected static \UnitEnum|string|null $navigationGroup = 'Network';
    protected static ?string $navigationLabel = 'Live RADIUS Sessions';
    protected static ?int $navigationSort = 5;
    protected string $view = 'filament.pages.live-radius-sessions';

    public static function getNavigationBadge(): ?string
    {
        return RadAcct::whereNull('acctstoptime')->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                RadAcct::query()
                    ->whereNull('acctstoptime')
                    ->orderBy('acctstarttime', 'desc')
            )
            ->columns([
                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nasipaddress')
                    ->label('NAS IP')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('framedipaddress')
                    ->label('User IP')
                    ->searchable(),

                TextColumn::make('nasporttype')
                    ->label('Port Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'PPPoE' => 'success',
                        'Wireless-802.11' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('acctstarttime')
                    ->label('Connected Since')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('acctinputoctets')
                    ->label('Upload')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / (1024 * 1024), 1) . ' MB' : '—'),

                TextColumn::make('acctoutputoctets')
                    ->label('Download')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / (1024 * 1024), 1) . ' MB' : '—'),

                TextColumn::make('callingstationid')
                    ->label('MAC Address')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->poll('30s')
            ->striped()
            ->defaultSort('acctstarttime', 'desc');
    }
}
