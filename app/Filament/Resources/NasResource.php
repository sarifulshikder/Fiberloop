<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NasResource\Pages;
use App\Models\Nas;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NasResource extends Resource
{
    protected static ?string $model = Nas::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-server-stack';
    protected static ?string $navigationLabel = 'RADIUS NAS Clients';
    protected static \UnitEnum|string|null $navigationGroup = 'Network';
    protected static ?int $navigationSort = 10;

    public static function getPluralLabel(): string
    {
        return 'NAS Clients';
    }

    public static function getSingularLabel(): string
    {
        return 'NAS Client';
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('NAS Identity & Network Details')->schema([
                Grid::make(2)->schema([
                    TextInput::make('nasname')
                        ->label('IP Address / Hostname')
                        ->placeholder('e.g. 192.168.1.1')
                        ->required()
                        ->maxLength(128),

                    TextInput::make('shortname')
                        ->label('Short Identifier')
                        ->placeholder('e.g. core-router-dhaka')
                        ->maxLength(32),
                ]),
                Grid::make(2)->schema([
                    Select::make('type')
                        ->label('NAS Vendor Type')
                        ->options([
                            'mikrotik' => 'MikroTik RouterOS',
                            'cisco' => 'Cisco Systems',
                            'chillispot' => 'ChilliSpot / CoovaChilli',
                            'other' => 'Other / Generic RADIUS NAS',
                        ])
                        ->default('mikrotik')
                        ->required(),

                    TextInput::make('ports')
                        ->label('Ports Count')
                        ->numeric()
                        ->placeholder('e.g. 1812'),
                ]),
            ]),

            Section::make('Security & SNMP')->schema([
                Grid::make(2)->schema([
                    TextInput::make('secret')
                        ->label('RADIUS Shared Secret')
                        ->password()
                        ->revealable()
                        ->required()
                        ->maxLength(255),

                    TextInput::make('community')
                        ->label('SNMP Community')
                        ->placeholder('e.g. public')
                        ->maxLength(50),
                ]),
                Grid::make(2)->schema([
                    TextInput::make('server')
                        ->label('Virtual Server Name')
                        ->placeholder('e.g. default')
                        ->maxLength(64),
                ]),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(2)
                    ->maxLength(200),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nasname')
                    ->label('IP / Host')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('shortname')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'mikrotik' => 'success',
                        'cisco' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('ports')
                    ->label('Ports')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(40),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'mikrotik' => 'MikroTik RouterOS',
                        'cisco' => 'Cisco Systems',
                        'chillispot' => 'ChilliSpot / CoovaChilli',
                        'other' => 'Other / Generic',
                    ]),
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
            ->defaultSort('nasname', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNases::route('/'),
            'create' => Pages\CreateNas::route('/create'),
            'edit' => Pages\EditNas::route('/{record}/edit'),
        ];
    }
}
