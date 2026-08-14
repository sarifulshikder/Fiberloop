<?php

namespace App\Filament\Customer\Resources;

use App\Filament\Customer\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wifi';
    protected static ?string $navigationLabel = 'My Subscription';
    protected static string|\UnitEnum|null $navigationGroup = 'Account';
    protected static ?int $navigationSort = 20;

    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'package.name', 'status'];
    }

    public static function getPluralLabel(): string
    {
        return 'Subscriptions';
    }

    public static function getSingularLabel(): string
    {
        return 'Subscription';
    }

    public static function getDescription(): string
    {
        return 'View your current subscription and package details';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Subscription Details')
                    ->schema([
                        TextInput::make('package.name')
                            ->label('Package Name')
                            ->readOnly(),
                        TextInput::make('package.download_speed')
                            ->label('Download Speed')
                            ->readOnly()
                            ->suffix('Mbps'),
                        TextInput::make('package.upload_speed')
                            ->label('Upload Speed')
                            ->readOnly()
                            ->suffix('Mbps'),
                        TextInput::make('package.price')
                            ->label('Monthly Price')
                            ->readOnly()
                            ->prefix('BDT'),
                        TextInput::make('billing_cycle')
                            ->label('Billing Cycle')
                            ->readOnly(),
                        TextInput::make('status')
                            ->label('Status')
                            ->readOnly(),
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->readOnly(),
                        DatePicker::make('next_billing_date')
                            ->label('Next Billing Date')
                            ->readOnly(),
                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->readOnly(),
                    ])->columns(2),

                Section::make('FUP Details')
                    ->schema([
                        TextInput::make('package.fup_threshold')
                            ->label('FUP Threshold')
                            ->readOnly()
                            ->suffix('GB'),
                        TextInput::make('package.fup_throttled_speed')
                            ->label('Speed After FUP')
                            ->readOnly()
                            ->suffix('Mbps'),
                        TextInput::make('fup_usage')
                            ->label('Current Month Usage')
                            ->readOnly()
                            ->suffix('GB'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('package.name')
                    ->label('Package')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('package.download_speed')
                    ->label('Download')
                    ->suffix('Mbps')
                    ->sortable(),
                TextColumn::make('package.upload_speed')
                    ->label('Upload')
                    ->suffix('Mbps')
                    ->sortable(),
                TextColumn::make('package.price')
                    ->label('Price')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->searchable()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        'terminated' => 'gray',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('next_billing_date')
                    ->label('Next Billing')
                    ->date()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label('Started')
                    ->date()
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->emptyStateDescription('No active subscription found')
            ->emptyStateIcon('heroicon-o-wifi')
            ->defaultSort('start_date', 'desc');
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
            'index' => Pages\ListSubscriptions::route('/'),
            'view' => Pages\ViewSubscription::route('/{record}'),
        ];
    }

    /**
     * Scope the query to the authenticated customer only
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('customer_id', auth('customer')->id());
    }
}
