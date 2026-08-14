<?php

namespace App\Filament\Customer\Widgets;

use App\Models\Invoice;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentInvoicesWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Recent Invoices';

    protected int | string | array $columnSpan = 2;

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Invoice::query()
            ->where('customer_id', auth('customer')->id())
            ->latest()
            ->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('invoice_number')
                ->label('Invoice #')
                ->sortable()
                ->searchable(),
            TextColumn::make('issue_date')
                ->label('Date')
                ->date()
                ->sortable(),
            TextColumn::make('due_date')
                ->label('Due Date')
                ->date()
                ->sortable(),
            SelectColumn::make('status')
                ->label('Status')
                ->sortable()
                ->searchable(),
            TextColumn::make('total')
                ->label('Total')
                ->money('BDT')
                ->sortable(),
            TextColumn::make('outstanding_amount')
                ->label('Outstanding')
                ->money('BDT')
                ->sortable()
                ->color(fn ($record) => $record->outstanding_amount > 0 ? 'danger' : 'success'),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }
}
