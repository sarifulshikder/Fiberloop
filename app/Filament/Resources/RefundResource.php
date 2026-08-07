<?php

namespace App\Filament\Resources;

use App\Enums\RefundStatus;
use App\Filament\Resources\RefundResource\Pages;
use App\Models\Refund;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Refunds';
    protected static string|\UnitEnum|null $navigationGroup = 'Billing & Payments';
    protected static ?int $navigationSort = 40;

    public static function getPluralLabel(): string
    {
        return 'Refunds';
    }
    public static function getSingularLabel(): string
    {
        return 'Refund';
    }
    public static function getDescription(): string
    {
        return 'Track customer refunds and reversals';
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Section::make('Refund Information')->schema([
                Grid::make(3)->schema([
                    TextInput::make('refund_number')->label('Refund #')->readOnly(),
                    Select::make('customer_id')->label('Customer')->required()->relationship('customer', 'full_name'),
                    Select::make('payment_id')->label('Payment')->relationship('payment', 'gateway_reference'),
                ]),
                Grid::make(3)->schema([
                    TextInput::make('amount')->label('Amount (poysha)')->required()->numeric()->minValue(0),
                    TextInput::make('fee_amount')->label('Fee (poysha)')->numeric()->minValue(0),
                    TextInput::make('net_amount')->label('Net (poysha)')->required()->numeric()->minValue(0),
                ]),
                Grid::make(2)->schema([
                    Select::make('status')->label('Status')->required()->options(RefundStatus::class),
                    TextInput::make('reason')->label('Reason')->required()->maxLength(255),
                ]),
            ]),
            Section::make('Gateway & Notes')->schema([
                Grid::make(2)->schema([
                    TextInput::make('gateway_reference')->label('Gateway Ref')->nullable(),
                    TextInput::make('gateway_response')->label('Gateway Resp')->nullable(),
                ]),
                MarkdownEditor::make('notes')->label('Notes')->nullable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('refund_number')->label('Refund #')->searchable()->sortable(),
            TextColumn::make('customer.full_name')->label('Customer')->searchable()->sortable(),
            TextColumn::make('amount')->label('Amount (BDT)')->state(fn ($r) => number_format($r->amount / 100, 2))->sortable(),
            SelectColumn::make('status')->label('Status')->options(RefundStatus::class)->sortable(),
            TextColumn::make('reason')->label('Reason')->searchable(),
            TextColumn::make('created_at')->label('Created')->dateTime()->sortable()->toggleable(true),
        ])->filters([
            SelectFilter::make('status')->label('Status')->options(RefundStatus::class)->multiple(),
        ])->actions([ViewAction::make(), EditAction::make()])->bulkActions([
            BulkActionGroup::make([DeleteBulkAction::make(), ExportBulkAction::make()]),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRefunds::route('/'),
            'create' => Pages\CreateRefund::route('/create'),
            'view' => Pages\ViewRefund::route('/{record}'),
            'edit' => Pages\EditRefund::route('/{record}/edit'),
        ];
    }
}
