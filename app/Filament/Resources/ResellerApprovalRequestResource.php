<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResellerApprovalRequestResource\Pages;
use App\Models\ResellerApprovalRequest;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ResellerApprovalRequestResource extends Resource
{
    protected static ?string $model = ResellerApprovalRequest::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static \UnitEnum|string|null $navigationGroup = 'Resellers';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Approval Requests';

    public static function getNavigationBadge(): ?string
    {
        return (string) ResellerApprovalRequest::pending()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $form): Schema
    {
        return $form->components([]); // Read-only view
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reseller.name')->label('Reseller')->searchable()->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state) => 'info')
                    ->formatStateUsing(fn ($state) => str_replace('_', ' ', ucfirst($state))),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                TextColumn::make('requestedBy.name')->label('Requested By')->searchable(),
                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable()->label('Requested At'),
                TextColumn::make('approved_at')->dateTime('d M Y H:i')->sortable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
                SelectFilter::make('type')->options([
                    'large_discount' => 'Large Discount',
                    'package_change' => 'Package Change',
                    'price_override' => 'Price Override',
                    'wallet_withdrawal' => 'Wallet Withdrawal',
                ]),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ResellerApprovalRequest $record) => $record->status === 'pending')
                    ->action(function (ResellerApprovalRequest $record) {
                        $record->update([
                            'status' => 'approved',
                            'reviewed_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);
                        activity()->performedOn($record)->log('Approval request approved');
                        Notification::make()->title('Request approved')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ResellerApprovalRequest $record) => $record->status === 'pending')
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Reason for rejection')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (ResellerApprovalRequest $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_by' => auth()->id(),
                            'rejected_at' => now(),
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        activity()->performedOn($record)->log('Approval request rejected: ' . $data['rejection_reason']);
                        Notification::make()->title('Request rejected')->danger()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResellerApprovalRequests::route('/'),
            'view' => Pages\ViewResellerApprovalRequest::route('/{record}'),
        ];
    }
}
