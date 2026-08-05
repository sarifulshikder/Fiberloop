<?php

namespace App\Filament\Resources;

use App\Enums\PackageChangeRequestStatus;
use App\Filament\Resources\PackageChangeRequestResource\Pages;
use App\Models\PackageChangeRequest;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PackageChangeRequestResource extends Resource
{
    protected static ?string $model = PackageChangeRequest::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Package Change Requests';
    protected static \UnitEnum|string|null $navigationGroup = 'CRM';
    protected static ?int $navigationSort = 40;

    public static function getPluralLabel(): string
    {
        return 'Package Change Requests';
    }

    public static function getSingularLabel(): string
    {
        return 'Package Change Request';
    }

    public static function getDescription(): string
    {
        return 'Manage customer package change, upgrade, and downgrade requests';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Request Details')
                    ->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->options(Customer::query()->pluck('full_name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('subscription_id')
                            ->label('Subscription')
                            ->options(fn () => \App\Models\Subscription::query()->pluck('uuid', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('current_package_id')
                            ->label('Current Package')
                            ->options(Package::query()->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('requested_package_id')
                            ->label('Requested Package')
                            ->options(Package::query()->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('status')
                            ->label('Status')
                            ->options(PackageChangeRequestStatus::class)
                            ->default(PackageChangeRequestStatus::PENDING->value)
                            ->required(),
                        Select::make('created_by')
                            ->label('Created By')
                            ->options(User::query()->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('approved_by')
                            ->label('Approved By')
                            ->options(User::query()->pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),
                        DatePicker::make('approved_at')
                            ->label('Approved At')
                            ->nullable(),
                        DatePicker::make('effective_date')
                            ->label('Effective Date')
                            ->nullable(),
                    ])->columns(2),

                Section::make('Additional Information')
                    ->schema([
                        Select::make('type')
                            ->label('Change Type')
                            ->options(['upgrade' => 'Upgrade', 'downgrade' => 'Downgrade', 'change' => 'Package Change'])
                            ->required(),
                        TextInput::make('proration_amount')
                            ->label('Proration Amount (poysha)')
                            ->numeric()
                            ->nullable(),
                        MarkdownEditor::make('reason')
                            ->label('Reason')
                            ->columnSpanFull()
                            ->nullable(),
                        MarkdownEditor::make('approval_notes')
                            ->label('Approval Notes')
                            ->columnSpanFull()
                            ->nullable(),
                        MarkdownEditor::make('rejection_reason')
                            ->label('Rejection Reason')
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
                    ->sortable(),
                TextColumn::make('customer.full_name')
                    ->label('Customer')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('subscription.uuid')
                    ->label('Subscription')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('currentPackage.name')
                    ->label('Current Package')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('requestedPackage.name')
                    ->label('Requested Package')
                    ->sortable()
                    ->searchable(),
                SelectColumn::make('status')
                    ->label('Status')
                    ->options(PackageChangeRequestStatus::class)
                    ->sortable()
                    ->searchable(),
                SelectColumn::make('type')
                    ->label('Type')
                    ->options(['upgrade' => 'Upgrade', 'downgrade' => 'Downgrade', 'change' => 'Package Change'])
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('effective_date')
                    ->label('Effective Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(PackageChangeRequestStatus::class)
                    ->multiple(),
                SelectFilter::make('type')
                    ->label('Change Type')
                    ->options(['upgrade' => 'Upgrade', 'downgrade' => 'Downgrade', 'change' => 'Package Change'])
                    ->multiple(),
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->options(Customer::query()->pluck('full_name', 'id'))
                    ->multiple()
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateDescription('No package change requests found')
            ->emptyStateIcon('heroicon-o-arrow-path');
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
            'index' => Pages\ListPackageChangeRequests::route('/'),
            'create' => Pages\CreatePackageChangeRequest::route('/create'),
            'view' => Pages\ViewPackageChangeRequest::route('/{record}'),
            'edit' => Pages\EditPackageChangeRequest::route('/{record}/edit'),
        ];
    }
}
