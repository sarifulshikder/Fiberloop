<?php

namespace App\Filament\Customer\Resources;

use App\Filament\Customer\Resources\TicketResource\Pages;
use App\Models\Ticket;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';
    protected static ?string $navigationLabel = 'My Tickets';
    protected static string|\UnitEnum|null $navigationGroup = 'Support';
    protected static ?int $navigationSort = 10;

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'description', 'status'];
    }

    public static function getPluralLabel(): string
    {
        return 'Tickets';
    }

    public static function getSingularLabel(): string
    {
        return 'Ticket';
    }

    public static function getDescription(): string
    {
        return 'Create and manage your support tickets';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('category')
                    ->label('Category')
                    ->options(\App\Enums\TicketCategory::class)
                    ->required(),
                Select::make('priority')
                    ->label('Priority')
                    ->options(\App\Enums\TicketPriority::class)
                    ->required()
                    ->default(\App\Enums\TicketPriority::MEDIUM),
                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255),
                MarkdownEditor::make('description')
                    ->label('Description')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('Ticket #')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Title')
                    ->sortable()
                    ->searchable(),
                SelectColumn::make('category')
                    ->label('Category')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable()
                    ->searchable()
                    ->color(fn ($state) => match ($state) {
                        'low' => 'gray',
                        'medium' => 'blue',
                        'high' => 'warning',
                        'critical' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->searchable()
                    ->color(fn ($state) => match ($state) {
                        'open' => 'blue',
                        'in_progress' => 'warning',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('sla_due_at')
                    ->label('SLA Due')
                    ->dateTime()
                    ->sortable()
                    ->color(fn ($record) => $record->sla_breached ? 'danger' : ($record->sla_due_soon ? 'warning' : 'gray')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(\App\Enums\TicketStatus::class)
                    ->multiple(),
                SelectFilter::make('category')
                    ->label('Category')
                    ->options(\App\Enums\TicketCategory::class)
                    ->multiple(),
                SelectFilter::make('priority')
                    ->label('Priority')
                    ->options(\App\Enums\TicketPriority::class)
                    ->multiple(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->emptyStateDescription('No tickets found. Create your first ticket to get support.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-ellipsis')
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'view' => Pages\ViewTicket::route('/{record}'),
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

    /**
     * Only allow customers to create tickets for themselves
     */
    protected static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['customer_id'] = auth('customer')->id();
        $data['created_by'] = auth('customer')->id();
        return $data;
    }

    /**
     * Only allow customers to edit their own tickets (limited fields)
     */
    protected static function mutateFormDataBeforeSave(array $data, $record): array
    {
        if ($record) {
            // Prevent customers from changing assigned_to or status
            unset($data['assigned_to']);
            unset($data['status']);
        }
        return $data;
    }
}
