<?php

namespace App\Filament\Resources\ProcurementResource\Schemas;

use App\Enums\ProcurementStatus;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class ProcurementForm
{
    public static function schema(): array
    {
        return [
            Section::make('Basic Information')
                ->schema([
                    TextInput::make('po_number')
                        ->label('PO Number')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(255),
                    MarkdownEditor::make('description')
                        ->label('Description')
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Financial Information')
                ->schema([
                    TextInput::make('subtotal')
                        ->label('Subtotal (BDT)')
                        ->numeric()
                        ->prefix('à§³')
                        ->helperText('Amount in poysha (BDT × 100)'),
                    TextInput::make('tax_amount')
                        ->label('Tax Amount (BDT)')
                        ->numeric()
                        ->prefix('à§³')
                        ->helperText('Amount in poysha (BDT × 100)'),
                    TextInput::make('shipping_cost')
                        ->label('Shipping Cost (BDT)')
                        ->numeric()
                        ->prefix('à§³')
                        ->helperText('Amount in poysha (BDT × 100)'),
                    TextInput::make('total_amount')
                        ->label('Total Amount (BDT)')
                        ->numeric()
                        ->prefix('à§³')
                        ->helperText('Amount in poysha (BDT × 100)'),
                    Select::make('currency')
                        ->label('Currency')
                        ->options(['BDT' => 'BDT - Bangladeshi Taka'])
                        ->default('BDT'),
                ])->columns(2),

            Section::make('Supplier & Dates')
                ->schema([
                    Select::make('supplier_id')
                        ->label('Supplier')
                        ->options(User::query()->whereHas('roles', fn ($q) => $q->where('name', 'reseller'))->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                    DatePicker::make('order_date')
                        ->label('Order Date'),
                    DatePicker::make('expected_delivery_date')
                        ->label('Expected Delivery Date'),
                    DatePicker::make('actual_delivery_date')
                        ->label('Actual Delivery Date'),
                    DatePicker::make('approved_at')
                        ->label('Approved At'),
                ])->columns(2),

            Section::make('Shipping Information')
                ->schema([
                    TextInput::make('tracking_number')
                        ->label('Tracking Number')
                        ->maxLength(255),
                    TextInput::make('shipping_method')
                        ->label('Shipping Method')
                        ->maxLength(100),
                ])->columns(2),

            Section::make('Status & Priority')
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options(ProcurementStatus::class)
                        ->default(ProcurementStatus::DRAFT->value)
                        ->required(),
                    Select::make('priority')
                        ->label('Priority')
                        ->options([
                            'low' => 'Low',
                            'medium' => 'Medium',
                            'high' => 'High',
                            'urgent' => 'Urgent',
                        ])
                        ->default('medium'),
                    Select::make('approved_by')
                        ->label('Approved By')
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
        ];
    }
}
