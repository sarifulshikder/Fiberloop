<?php

namespace App\Filament\Resources;

use App\Enums\LeadStatus;
use App\Filament\Resources\LeadResource\Pages;
use App\Models\Lead;
use App\Models\NetworkDevice;
use App\Models\Olt;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Leads';
    protected static string|\UnitEnum|null $navigationGroup = 'CRM';
    protected static ?int $navigationSort = 20;

    public static function getPluralLabel(): string
    {
        return 'Leads';
    }

    public static function getSingularLabel(): string
    {
        return 'Lead';
    }

    public static function getDescription(): string
    {
        return 'Manage sales leads and pipeline';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Lead Information')
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
                    ])->columns(2),

                Section::make('Address & Location')
                    ->schema([
                        TextInput::make('address')
                            ->maxLength(500),
                        TextInput::make('latitude')
                            ->label('Latitude')
                            ->maxLength(50),
                        TextInput::make('longitude')
                            ->label('Longitude')
                            ->maxLength(50),
                        TextInput::make('area')
                            ->maxLength(100),
                        TextInput::make('zone')
                            ->maxLength(100),
                    ])->columns(2),

                Section::make('Lead Status & Assignment')
                    ->schema([
                        Select::make('status')
                            ->label('Lead Status')
                            ->options(LeadStatus::class)
                            ->default(LeadStatus::NEW->value)
                            ->required(),
                        Select::make('assigned_to')
                            ->label('Assigned To')
                            ->options(User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'support_agent', 'billing_agent']))->pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),
                        Select::make('priority')
                            ->label('Priority')
                            ->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'])
                            ->default('medium'),
                    ])->columns(2),

                Section::make('Site Survey / Feasibility')
                    ->schema([
                        Select::make('is_feasible')
                            ->label('Feasible?')
                            ->options([
                                true => 'Yes',
                                false => 'No',
                            ])
                            ->nullable(),
                        Select::make('assigned_olt_id')
                            ->label('Assigned OLT')
                            ->options(Olt::query()->pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),
                        Select::make('assigned_network_device_id')
                            ->label('Assigned Network Device')
                            ->options(NetworkDevice::query()->pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),
                        DatePicker::make('site_survey_date')
                            ->label('Site Survey Date')
                            ->nullable(),
                        MarkdownEditor::make('feasibility_notes')
                            ->label('Feasibility Notes')
                            ->columnSpanFull()
                            ->nullable(),
                    ]),

                Section::make('Conversion & Source')
                    ->schema([
                        Select::make('source')
                            ->label('Lead Source')
                            ->options(['web' => 'Web', 'phone' => 'Phone', 'referral' => 'Referral', 'field' => 'Field', 'reseller' => 'Reseller', 'other' => 'Other'])
                            ->default('phone'),
                        TextInput::make('referral_code')
                            ->label('Referral Code')
                            ->maxLength(50)
                            ->nullable(),
                        MarkdownEditor::make('notes')
                            ->label('Notes')
                            ->columnSpanFull()
                            ->nullable(),
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
                SelectColumn::make('status')
                    ->label('Status')
                    ->options(LeadStatus::class)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('address')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('area')
                    ->label('Area')
                    ->searchable(),
                TextColumn::make('zone')
                    ->label('Zone')
                    ->searchable(),
                IconColumn::make('is_feasible')
                    ->label('Feasible')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(LeadStatus::class)
                    ->multiple(),
                SelectFilter::make('source')
                    ->label('Source')
                    ->options(['web' => 'Web', 'phone' => 'Phone', 'referral' => 'Referral', 'field' => 'Field', 'reseller' => 'Reseller', 'other' => 'Other'])
                    ->multiple(),
                SelectFilter::make('area')
                    ->label('Area')
                    ->options(fn () => \App\Models\Lead::query()->distinct('area')->pluck('area', 'area')->toArray())
                    ->multiple(),
                SelectFilter::make('zone')
                    ->label('Zone')
                    ->options(fn () => \App\Models\Lead::query()->distinct('zone')->pluck('zone', 'zone')->toArray())
                    ->multiple(),
                SelectFilter::make('priority')
                    ->label('Priority')
                    ->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'])
                    ->multiple(),
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
            ->emptyStateDescription('No leads found')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
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
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'view' => Pages\ViewLead::route('/{record}'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}
