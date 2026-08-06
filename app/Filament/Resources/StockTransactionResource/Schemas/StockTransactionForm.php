<?php

namespace App\Filament\Resources\StockTransactionResource\Schemas;

use App\Enums\InventoryStatus;
use App\Enums\StockTransactionReason;
use App\Enums\StockTransactionType;
use App\Models\Customer;
use App\Models\FieldJob;
use App\Models\InventoryItem;
use App\Models\Subscription;
use App\Models\User;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockTransactionForm
{
    public static function schema(): Schema
    {
        return Schema::make([
            Section::make('Transaction Information')
                ->schema([
                    Select::make('transaction_type')
                        ->label('Transaction Type')
                        ->options(StockTransactionType::class)
                        ->required(),
                    Select::make('reason')
                        ->label('Reason')
                        ->options(StockTransactionReason::class)
                        ->required(),
                    TextInput::make('reference_number')
                        ->label('Reference Number')
                        ->maxLength(100),
                    TextInput::make('quantity')
                        ->label('Quantity')
                        ->numeric()
                        ->default(1)
                        ->minValue(1),
                    TextInput::make('unit_cost')
                        ->label('Unit Cost (BDT)')
                        ->numeric()
                        ->prefix('à§³')
                        ->helperText('Amount in poysha (BDT × 100)'),
                    TextInput::make('total_cost')
                        ->label('Total Cost (BDT)')
                        ->numeric()
                        ->prefix('à§³')
                        ->helperText('Amount in poysha (BDT × 100)'),
                ])->columns(2),

            Section::make('Inventory Item')
                ->schema([
                    Select::make('inventory_item_id')
                        ->label('Inventory Item')
                        ->options(InventoryItem::query()->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ]),

            Section::make('Related Entities')
                ->schema([
                    Select::make('user_id')
                        ->label('User')
                        ->options(User::query()->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                    Select::make('customer_id')
                        ->label('Customer')
                        ->options(Customer::query()->pluck('full_name', 'id'))
                        ->searchable()
                        ->nullable(),
                    Select::make('field_job_id')
                        ->label('Field Job')
                        ->options(FieldJob::query()->pluck('title', 'id'))
                        ->searchable()
                        ->nullable(),
                    Select::make('subscription_id')
                        ->label('Subscription')
                        ->options(Subscription::query()->pluck('uuid', 'id'))
                        ->searchable()
                        ->nullable(),
                ])->columns(2),

            Section::make('Status Changes')
                ->schema([
                    Select::make('previous_status')
                        ->label('Previous Status')
                        ->options(InventoryStatus::class)
                        ->nullable(),
                    Select::make('new_status')
                        ->label('New Status')
                        ->options(InventoryStatus::class)
                        ->nullable(),
                    TextInput::make('previous_location')
                        ->label('Previous Location')
                        ->maxLength(255)
                        ->nullable(),
                    TextInput::make('new_location')
                        ->label('New Location')
                        ->maxLength(255)
                        ->nullable(),
                    Select::make('previous_holder_id')
                        ->label('Previous Holder')
                        ->options(User::query()->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                    Select::make('new_holder_id')
                        ->label('New Holder')
                        ->options(User::query()->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                ])->columns(2),

            Section::make('Notes')
                ->schema([
                    MarkdownEditor::make('notes')
                        ->label('Notes')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
