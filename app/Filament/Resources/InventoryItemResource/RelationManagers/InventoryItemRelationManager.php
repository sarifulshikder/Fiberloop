<?php

namespace App\Filament\Resources\InventoryItemResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InventoryItemRelationManager extends RelationManager
{
    protected static string $relationship = 'stockTransactions';

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Transaction Notes')
                    ->schema([
                        TextInput::make('notes')
                            ->required()
                            ->maxLength(255),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('transaction_type'),
                TextColumn::make('reason'),
                TextColumn::make('quantity'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
