<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Information')
                    ->description('Basic account credentials and identity')
                    ->icon('heroicon-o-user-circle')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. John Doe')
                            ->columnSpanFull(),

                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('user@fiberloop.com'),

                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('+8801700000000'),

                        FileUpload::make('avatar')
                            ->label('Profile Photo')
                            ->image()
                            ->directory('avatars')
                            ->nullable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Password')
                    ->description('Leave blank to keep existing password when editing')
                    ->icon('heroicon-o-lock-closed')
                    ->columns(2)
                    ->schema([
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->minLength(8)
                            ->maxLength(255)
                            ->placeholder('Minimum 8 characters'),

                        TextInput::make('password_confirmation')
                            ->label('Confirm Password')
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(false)
                            ->maxLength(255),
                    ]),

                Section::make('Roles & Permissions')
                    ->description('Assign roles to control what this user can access in the admin panel')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Select::make('roles')
                            ->label('Roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->getOptionLabelFromRecordUsing(fn (Role $record) => ucwords(str_replace('_', ' ', $record->name)))
                            ->helperText('Staff roles (super_admin, admin, noc_engineer, etc.) grant access to the admin panel.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Status & Settings')
                    ->description('Account status and locale preferences')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Account Active')
                            ->helperText('Inactive users cannot log in')
                            ->default(true),

                        Toggle::make('is_super_admin')
                            ->label('Super Admin')
                            ->helperText('Super admins bypass all permission checks')
                            ->default(false),

                        TextInput::make('locale')
                            ->label('Locale')
                            ->default('en')
                            ->maxLength(10),

                        TextInput::make('timezone')
                            ->label('Timezone')
                            ->default('Asia/Dhaka')
                            ->maxLength(50),
                    ]),

                Section::make('Login Activity')
                    ->description('Read-only login history')
                    ->icon('heroicon-o-clock')
                    ->columns(2)
                    ->visibleOn('edit')
                    ->schema([
                        Placeholder::make('last_login_at')
                            ->label('Last Login')
                            ->content(fn ($record) => $record?->last_login_at?->diffForHumans() ?? 'Never'),

                        Placeholder::make('last_login_ip')
                            ->label('Last Login IP')
                            ->content(fn ($record) => $record?->last_login_ip ?? '—'),
                    ]),
            ]);
    }
}
