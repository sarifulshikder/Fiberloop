<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Enums\NoteType;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerNoteRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $label = 'Notes & Timeline';

    protected static ?string $title = 'Customer Notes';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('type')
                    ->label('Note Type')
                    ->options(NoteType::class)
                    ->required(),
                Select::make('category')
                    ->label('Category')
                    ->options([
                        'call' => 'Call',
                        'complaint' => 'Complaint',
                        'technician_visit' => 'Technician Visit',
                        'payment' => 'Payment',
                        'support' => 'Support',
                        'sales' => 'Sales',
                        'other' => 'Other',
                    ])
                    ->required(),
                MarkdownEditor::make('content')
                    ->label('Content')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                SelectColumn::make('type')
                    ->label('Type')
                    ->options(NoteType::class)
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Category')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('content')
                    ->label('Content')
                    ->limit(100)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateDescription('No notes found for this customer')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-ellipsis');
    }
}
