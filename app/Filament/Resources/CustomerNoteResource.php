<?php

namespace App\Filament\Resources;

use App\Enums\NoteType;
use App\Filament\Resources\CustomerNoteResource\Pages;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerNoteResource extends Resource
{
    protected static ?string $model = CustomerNote::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';
    protected static ?string $navigationLabel = 'Customer Notes';
    protected static string|\UnitEnum|null $navigationGroup = 'CRM';
    protected static ?int $navigationSort = 30;

    public static function getPluralLabel(): string
    {
        return 'Customer Notes';
    }

    public static function getSingularLabel(): string
    {
        return 'Customer Note';
    }

    public static function getDescription(): string
    {
        return 'Manage customer notes and timeline';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('customer_id')
                    ->label('Customer')
                    ->relationship(
                        name: 'customer',
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('first_name')->orderBy('last_name'),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Customer $record) => "{$record->first_name} {$record->last_name}"
                    )
                    ->searchable(['first_name', 'last_name', 'phone'])
                    ->required(),
                Select::make('type')
                    ->label('Note Type')
                    ->options(NoteType::class)
                    ->required(),
                Select::make('created_by')
                    ->label('Created By')
                    ->options(User::query()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                MarkdownEditor::make('content')
                    ->label('Content')
                    ->required()
                    ->columnSpanFull(),
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
                SelectColumn::make('type')
                    ->label('Type')
                    ->options(NoteType::class)
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
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(NoteType::class)
                    ->multiple(),
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship(
                        name: 'customer',
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('first_name')->orderBy('last_name'),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Customer $record) => "{$record->first_name} {$record->last_name}"
                    )
                    ->searchable(['first_name', 'last_name', 'phone'])
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
            ->emptyStateDescription('No customer notes found')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-ellipsis');
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
            'index' => Pages\ListCustomerNotes::route('/'),
            'create' => Pages\CreateCustomerNote::route('/create'),
            'view' => Pages\ViewCustomerNote::route('/{record}'),
            'edit' => Pages\EditCustomerNote::route('/{record}/edit'),
        ];
    }
}
