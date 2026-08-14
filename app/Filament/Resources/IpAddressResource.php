<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IpAddressResource\Pages\CreateIpAddress;
use App\Filament\Resources\IpAddressResource\Pages\EditIpAddress;
use App\Filament\Resources\IpAddressResource\Pages\ListIpAddresses;
use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\IpPool;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IpAddressResource extends Resource
{
    protected static ?string $model = IpAddress::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static string|\UnitEnum|null $navigationGroup = 'Network';
    protected static ?string $navigationLabel = 'IP Addresses';
    protected static ?int $navigationSort = 7;
    protected static ?string $recordTitleAttribute = 'ip_address';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('IP Address Details')
                ->schema([
                    \Filament\Forms\Components\Select::make('ip_pool_id')
                        ->label('IP Pool')
                        ->options(IpPool::pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('ip_address')
                        ->label('IP Address')
                        ->required()
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('mac_address')
                        ->label('MAC Address')
                        ->maxLength(255),
                    \Filament\Forms\Components\Select::make('status')
                        ->options([
                            'available' => 'Available',
                            'assigned'  => 'Assigned',
                            'reserved'  => 'Reserved',
                            'blocked'   => 'Blocked',
                        ])
                        ->default('available')
                        ->required(),
                    \Filament\Forms\Components\Select::make('customer_id')
                        ->label('Customer')
                        ->relationship(
                            name: 'customer',
                            modifyQueryUsing: fn (Builder $query) => $query->orderBy('first_name')->orderBy('last_name'),
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (Customer $record) => "{$record->first_name} {$record->last_name}"
                        )
                        ->searchable(['first_name', 'last_name', 'phone'])
                        ->nullable(),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->rows(3),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                CheckboxColumn::make('id')
                    ->label('Select')
                    ->width(40),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('mac_address')
                    ->label('MAC Address')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string|null $state): string => match ($state) {
                        'available' => 'success',
                        'assigned'  => 'info',
                        'reserved'  => 'warning',
                        'blocked'   => 'danger',
                        default     => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('ipPool.name')
                    ->label('Pool')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->customer?->full_name ?? '—'),
                TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'assigned'  => 'Assigned',
                        'reserved'  => 'Reserved',
                        'blocked'   => 'Blocked',
                    ]),
                SelectFilter::make('ip_pool_id')
                    ->label('Pool')
                    ->options(IpPool::pluck('name', 'id'))
                    ->searchable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListIpAddresses::route('/'),
            'create' => CreateIpAddress::route('/create'),
            'edit'   => EditIpAddress::route('/{record}/edit'),
        ];
    }
}
