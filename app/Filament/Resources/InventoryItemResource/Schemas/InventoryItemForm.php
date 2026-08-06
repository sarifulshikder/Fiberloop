<?php

namespace App\Filament\Resources\InventoryItemResource\Schemas;

use App\Enums\InventoryStatus;
use App\Models\Customer;
use App\Models\Reseller;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventoryItemForm
{
    public static function schema(): Schema
    {
        return Schema::make([
            Section::make('Basic Information')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Select::make('item_type')
                        ->label('Item Type')
                        ->options([
                            'ONT' => 'ONT/ONU',
                            'OLT' => 'OLT',
                            'router' => 'Router',
                            'switch' => 'Switch',
                            'cable' => 'Cable',
                            'accessory' => 'Accessory',
                            'other' => 'Other',
                        ])
                        ->required()
                        ->default('ONT'),
                    TextInput::make('category')
                        ->maxLength(100),
                    TextInput::make('brand')
                        ->maxLength(100),
                    TextInput::make('model')
                        ->maxLength(100),
                ])->columns(2),

            Section::make('Identification')
                ->schema([
                    TextInput::make('serial_number')
                        ->maxLength(255),
                    TextInput::make('imei')
                        ->label('IMEI')
                        ->maxLength(100),
                    TextInput::make('mac_address')
                        ->label('MAC Address')
                        ->maxLength(17),
                    TextInput::make('barcode')
                        ->maxLength(255),
                    TextInput::make('asset_tag')
                        ->label('Asset Tag')
                        ->maxLength(100),
                ])->columns(2),

            Section::make('Location & Assignment')
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options(InventoryStatus::class)
                        ->default(InventoryStatus::IN_STOCK->value)
                        ->required(),
                    TextInput::make('warehouse')
                        ->maxLength(100),
                    TextInput::make('bin_location')
                        ->label('Bin Location')
                        ->maxLength(100),
                    TextInput::make('assigned_location')
                        ->label('Assigned Location')
                        ->maxLength(255),
                    Select::make('customer_id')
                        ->label('Assigned Customer')
                        ->options(Customer::query()->pluck('full_name', 'id'))
                        ->searchable()
                        ->nullable(),
                    Select::make('reseller_id')
                        ->label('Assigned Reseller')
                        ->options(Reseller::query()->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                ])->columns(2),

            Section::make('Financial Information')
                ->schema([
                    TextInput::make('purchase_price')
                        ->label('Purchase Price (BDT)')
                        ->numeric()
                        ->prefix('à§³')
                        ->helperText('Amount in poysha (BDT × 100)'),
                    TextInput::make('selling_price')
                        ->label('Selling Price (BDT)')
                        ->numeric()
                        ->prefix('à§³')
                        ->helperText('Amount in poysha (BDT × 100)'),
                    DatePicker::make('purchase_date')
                        ->label('Purchase Date'),
                    TextInput::make('purchase_invoice_id')
                        ->label('Purchase Invoice')
                        ->maxLength(100),
                    Select::make('supplier_id')
                        ->label('Supplier')
                        ->options(User::query()->whereHas('roles', fn ($q) => $q->where('name', 'reseller'))->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                ])->columns(2),

            Section::make('Warranty Information')
                ->schema([
                    DatePicker::make('warranty_start')
                        ->label('Warranty Start Date'),
                    DatePicker::make('warranty_end')
                        ->label('Warranty End Date'),
                    TextInput::make('warranty_months')
                        ->label('Warranty Months')
                        ->numeric()
                        ->minValue(0),
                ])->columns(3),

            Section::make('Assignment Timeline')
                ->schema([
                    DatePicker::make('assigned_at')
                        ->label('Assigned At'),
                    DatePicker::make('returned_at')
                        ->label('Returned At'),
                    TextInput::make('assignment_notes')
                        ->label('Assignment Notes')
                        ->maxLength(500),
                ])->columns(2),

            Section::make('Condition & Specifications')
                ->schema([
                    TextInput::make('condition')
                        ->label('Condition')
                        ->maxLength(100)
                        ->default('New'),
                    MarkdownEditor::make('condition_notes')
                        ->label('Condition Notes')
                        ->columnSpanFull(),
                    MarkdownEditor::make('specifications')
                        ->label('Specifications')
                        ->columnSpanFull(),
                    MarkdownEditor::make('notes')
                        ->label('Notes')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
