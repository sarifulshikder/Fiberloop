<?php

namespace App\Filament\Resources;

use App\Enums\CreditNoteStatus;
use App\Filament\Resources\CreditNoteResource\Pages;
use App\Models\CreditNote;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class CreditNoteResource extends Resource
{
    protected static ?string $model = CreditNote::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-minus';
    protected static ?string $navigationLabel = 'Credit Notes';
    protected static \UnitEnum|string|null $navigationGroup = 'Billing & Payments';
    protected static ?int $navigationSort = 30;

    public static function getPluralLabel(): string { return 'Credit Notes'; }
    public static function getSingularLabel(): string { return 'Credit Note'; }
    public static function getDescription(): string { return 'Manage customer credit notes and refunds'; }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Credit Note Information')->schema([
                Grid::make(3)->schema([
                    TextInput::make('credit_note_number')->label('Credit Note #')->readOnly(),
                    Select::make('customer_id')->label('Customer')->required()->relationship('customer', 'full_name'),
                    Select::make('invoice_id')->label('Invoice')->relationship('invoice', 'invoice_number'),
                ]),
                Grid::make(3)->schema([
                    DatePicker::make('issue_date')->label('Issue Date')->required(),
                    Select::make('status')->label('Status')->required()->options(CreditNoteStatus::class),
                    TextInput::make('reason')->label('Reason')->required()->maxLength(255),
                ]),
            ]),
            Section::make('Amounts')->schema([
                Grid::make(3)->schema([
                    TextInput::make('subtotal')->label('Subtotal (poysha)')->numeric()->minValue(0),
                    TextInput::make('tax_amount')->label('Tax (poysha)')->numeric()->minValue(0),
                    TextInput::make('total')->label('Total (poysha)')->required()->numeric()->minValue(0),
                ]),
            ]),
            Section::make('Notes')->schema([
                MarkdownEditor::make('notes')->label('Notes')->nullable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('credit_note_number')->label('CN #')->searchable()->sortable(),
            TextColumn::make('customer.full_name')->label('Customer')->searchable()->sortable(),
            TextColumn::make('total')->label('Amount (BDT)')->state(fn($r) => number_format($r->total/100,2))->sortable(),
            SelectColumn::make('status')->label('Status')->options(CreditNoteStatus::class)->sortable(),
            TextColumn::make('issue_date')->label('Issued')->date()->sortable(),
            TextColumn::make('created_at')->label('Created')->dateTime()->sortable()->toggleable(true),
        ])->filters([
            SelectFilter::make('status')->label('Status')->options(CreditNoteStatus::class)->multiple(),
        ])->actions([ViewAction::make(), EditAction::make()])->bulkActions([
            BulkActionGroup::make([DeleteBulkAction::make(), ExportBulkAction::make()]),
        ])->defaultSort('issue_date', 'desc');
    }

    public static function getPages(): array { return [
        'index' => Pages\ListCreditNotes::route('/'),
        'create' => Pages\CreateCreditNote::route('/create'),
        'view' => Pages\ViewCreditNote::route('/{record}'),
        'edit' => Pages\EditCreditNote::route('/{record}/edit'),
    ]; }
}
