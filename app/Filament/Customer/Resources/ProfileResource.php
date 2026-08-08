<?php

namespace App\Filament\Customer\Resources;

use App\Enums\ConnectionType;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Filament\Customer\Resources\ProfileResource\Pages\{ViewProfile, EditProfile, Index};

class ProfileResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationLabel = 'Profile';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = null;

    protected static ?string $recordTitleAttribute = 'first_name';

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
        return 'View and edit your profile';
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
                            ->maxLength(255)
                            ->readonly(), // email maybe not editable? We'll keep editable for now.
                        TextInput::make('phone')
                            ->required()
                            ->maxLength(20),
                        TextInput::make('alternate_phone')
                            ->maxLength(20),
                        DatePicker::make('date_of_birth'),
                        Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                            ]),
                    ])->columns(2),

                Section::make('KYC Documents')
                    ->schema([
                        FileUpload::make('nid_front_photo')
                            ->label('NID Front Photo')
                            ->directory('kyc-documents')
                            ->disk('encrypted')
                            ->visibility('private')
                            ->getUploadedFileNameForStorageUsing(fn ($component, $file) => $file->hashName())
                            ->maxSize(5 * 1024)
                            ->image()
                            ->imagePreviewHeight('150')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),

                        FileUpload::make('nid_back_photo')
                            ->label('NID Back Photo')
                            ->directory('kyc-documents')
                            ->disk('encrypted')
                            ->visibility('private')
                            ->getUploadedFileNameForStorageUsing(fn ($component, $file) => $file->hashName())
                            ->maxSize(5 * 1024)
                            ->image()
                            ->imagePreviewHeight('150')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),

                        FileUpload::make('signature_photo')
                            ->label('Signature')
                            ->directory('kyc-documents')
                            ->disk('encrypted')
                            ->visibility('private')
                            ->getUploadedFileNameForStorageUsing(fn ($component, $file) => $file->hashName())
                            ->maxSize(2 * 1024)
                            ->image()
                            ->imagePreviewHeight('100')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),

                        TextInput::make('nid_number')
                            ->label('NID Number')
                            ->maxLength(50),
                    ])->columns(2),

                Section::make('Addresses')
                    ->schema([
                        TextInput::make('service_address')
                            ->label('Service Address')
                            ->required()
                            ->maxLength(500),
                        TextInput::make('service_latitude')
                            ->label('Service Latitude')
                            ->maxLength(50),
                        TextInput::make('service_longitude')
                            ->label('Service Longitude')
                            ->maxLength(50),
                        TextInput::make('billing_address')
                            ->label('Billing Address')
                            ->maxLength(500),
                        TextInput::make('area')
                            ->maxLength(100),
                        TextInput::make('zone')
                            ->maxLength(100),
                    ])->columns(3),

                Section::make('Connection Details')
                    ->schema([
                        Select::make('connection_type')
                            ->label('Connection Type')
                            ->options(ConnectionType::class)
                            ->default(ConnectionType::PPPOE->value)
                            ->required(),
                        TextInput::make('radius_username')
                            ->maxLength(100),
                        TextInput::make('radius_password')
                            ->password()
                            ->maxLength(100),
                        TextInput::make('static_ip')
                            ->maxLength(45),
                        TextInput::make('mac_address')
                            ->maxLength(17),
                    ])->columns(2),

                Section::make('Status')
                    ->schema([
                        Select::make('status')
                            ->label('Customer Status')
                            ->options(CustomerStatus::class)
                            ->default(CustomerStatus::PENDING->value)
                            ->required(),
                        DatePicker::make('activated_at'),
                        DatePicker::make('suspended_at'),
                        DatePicker::make('terminated_at'),
                        TextInput::make('suspension_reason')
                            ->maxLength(500),
                        TextInput::make('termination_reason')
                            ->maxLength(500),
                    ])->columns(2),

                Section::make('Notes')
                    ->schema([
                        MarkdownEditor::make('notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
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
                TextColumn::make('nid_number')
                    ->label('NID')
                    ->sortable()
                    ->searchable(),
                SelectColumn::make('status')
                    ->label('Status')
                    ->options(CustomerStatus::class)
                    ->sortable()
                    ->searchable(),
                SelectColumn::make('connection_type')
                    ->label('Connection')
                    ->options(ConnectionType::class)
                    ->sortable(),
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
                TextColumn::make('activated_at')
                    ->label('Activated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                // No filters needed for single record
            ])
            ->actions([
                \Filament\Tables\Actions\ViewAction::make(),
                \Filament\Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // No bulk actions
            ])
            ->emptyStateDescription('No profile found')
            ->emptyStateIcon('heroicon-o-user');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Index::route('/'),
            'view' => ViewProfile::route('/{record}'),
            'edit' => EditProfile::route('/{record}/edit'),
        ];
    }

    /**
     * Scope the query to only the currently authenticated customer.
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('id', auth()->id());
    }
}