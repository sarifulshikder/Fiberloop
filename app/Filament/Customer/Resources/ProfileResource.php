<?php

namespace App\Filament\Customer\Resources;

use App\Filament\Customer\Resources\ProfileResource\Pages;
use App\Models\Customer;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProfileResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'My Profile';
    protected static string|\UnitEnum|null $navigationGroup = 'Account';
    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }

    public static function getPluralLabel(): string
    {
        return 'Profile';
    }

    public static function getSingularLabel(): string
    {
        return 'Profile';
    }

    public static function getDescription(): string
    {
        return 'View and manage your profile information';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Profile Information')
                    ->schema([
                        TextInput::make('first_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->required()
                            ->maxLength(20),
                        TextInput::make('alternate_phone')
                            ->maxLength(20),
                        DatePicker::make('date_of_birth'),
                        TextInput::make('gender')
                            ->maxLength(20),
                    ])->columns(2),

                Section::make('Addresses')
                    ->schema([
                        TextInput::make('service_address')
                            ->label('Service Address')
                            ->required()
                            ->maxLength(500)
                            ->readOnly(),
                        TextInput::make('billing_address')
                            ->label('Billing Address')
                            ->maxLength(500),
                        TextInput::make('area')
                            ->maxLength(100)
                            ->readOnly(),
                        TextInput::make('zone')
                            ->maxLength(100)
                            ->readOnly(),
                    ])->columns(2),

                Section::make('Connection Details')
                    ->schema([
                        TextInput::make('connection_type')
                            ->label('Connection Type')
                            ->readOnly(),
                        TextInput::make('static_ip')
                            ->label('Static IP')
                            ->maxLength(45)
                            ->readOnly(),
                        TextInput::make('mac_address')
                            ->label('MAC Address')
                            ->maxLength(17)
                            ->readOnly(),
                    ])->columns(2),

                Section::make('Status')
                    ->schema([
                        TextInput::make('status')
                            ->label('Customer Status')
                            ->readOnly(),
                        DatePicker::make('activated_at')
                            ->readOnly(),
                        DatePicker::make('suspended_at')
                            ->readOnly(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('phone')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('service_address')
                    ->label('Service Address')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('area')
                    ->label('Area')
                    ->searchable(),
                TextColumn::make('zone')
                    ->label('Zone')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->emptyStateDescription('No profile found')
            ->emptyStateIcon('heroicon-o-user-circle');
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
            'index' => Pages\ListProfiles::route('/'),
            'view' => Pages\ViewProfile::route('/{record}'),
            'edit' => Pages\EditProfile::route('/{record}/edit'),
        ];
    }

    /**
     * Scope the query to the authenticated customer only
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('id', auth('customer')->id());
    }
}
