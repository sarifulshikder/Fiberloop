<?php

namespace App\Filament\Resources;

use App\Enums\ConnectionType;
use App\Enums\CustomerStatus;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Models\Package;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Customers';
    protected static \UnitEnum|string|null $navigationGroup = 'CRM';
    protected static ?int $navigationSort = 10;

    public static function getPluralLabel(): string
    {
        return 'Customers';
    }

    public static function getSingularLabel(): string
    {
        return 'Customer';
    }

    public static function getDescription(): string
    {
        return 'Manage customer records, KYC documents, and service details';
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
                        Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                            ]),
                        Select::make('package_id')
                            ->label('Package')
                            ->options(Package::query()->pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),
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
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(CustomerStatus::class)
                    ->multiple(),
                SelectFilter::make('connection_type')
                    ->label('Connection Type')
                    ->options(ConnectionType::class)
                    ->multiple(),
                SelectFilter::make('area')
                    ->label('Area')
                    ->options(fn () => \App\Models\Customer::query()->distinct('area')->pluck('area', 'area')->filter()->toArray())
                    ->multiple(),
                SelectFilter::make('zone')
                    ->label('Zone')
                    ->options(fn () => \App\Models\Customer::query()->distinct('zone')->pluck('zone', 'zone')->filter()->toArray())
                    ->multiple(),
                SelectFilter::make('nid_number')
                    ->label('NID Number')
                    ->searchable()
                    ->multiple(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    \App\Filament\Resources\CustomerResource\Actions\SuspendBulkAction::make(),
                    \App\Filament\Resources\CustomerResource\Actions\SmsBulkAction::make(),
                    DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ])
            ->emptyStateDescription('No customers found')
            ->emptyStateIcon('heroicon-o-users');
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
