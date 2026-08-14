<?php

namespace App\Filament\Resources;

use App\Enums\ResellerStatus;
use App\Filament\Resources\ResellerResource\Pages;
use App\Models\Reseller;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ResellerResource extends Resource
{
    protected static ?string $model = Reseller::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Resellers';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $form): Schema
    {
        return $form->components([
            Section::make('Reseller Details')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('code')->required()->maxLength(50)
                        ->unique(ignoreRecord: true),
                ]),
                Grid::make(2)->schema([
                    TextInput::make('email')->email()->nullable(),
                    TextInput::make('phone')->required()->maxLength(20),
                ]),
                TextInput::make('alternate_phone')->nullable()->maxLength(20),
                Textarea::make('address')->rows(2)->nullable(),
                Select::make('parent_id')
                    ->label('Parent Reseller')
                    ->options(fn (?Reseller $record) => Reseller::query()
                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->placeholder('None (top-level reseller)'),
            ]),
            Section::make('Commission & Status')->schema([
                Grid::make(3)->schema([
                    Select::make('status')
                        ->options(array_combine(
                            array_map(fn ($s) => $s->value, ResellerStatus::cases()),
                            array_map(fn ($s) => $s->label(), ResellerStatus::cases())
                        ))
                        ->required()
                        ->default(ResellerStatus::ACTIVE->value),
                    TextInput::make('commission_rate')
                        ->label('Commission Rate (%)')
                        ->integer()
                        ->minValue(0)->maxValue(100)
                        ->default(0)
                        ->helperText('Percentage of payment amount. Takes priority over flat amount.'),
                    TextInput::make('commission_amount')
                        ->label('Flat Commission (poysha)')
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('Fixed poysha per payment when rate = 0. E.g. 5000 = ৳50.'),
                ]),
            ]),
            Section::make('Contract')->schema([
                Grid::make(2)->schema([
                    DatePicker::make('contract_start_date')->nullable(),
                    DatePicker::make('contract_end_date')->nullable(),
                ]),
                Textarea::make('contract_terms')->rows(3)->nullable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                CheckboxColumn::make('id')
                    ->label('Select')
                    ->width(40),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->searchable()->badge()->color('gray'),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state instanceof ResellerStatus ? $state : ResellerStatus::tryFrom((string) $state)) {
                        ResellerStatus::ACTIVE => 'success',
                        ResellerStatus::SUSPENDED => 'warning',
                        ResellerStatus::TERMINATED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ($state instanceof ResellerStatus ? $state : ResellerStatus::tryFrom((string) $state))?->label()),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('commission_rate')
                    ->label('Rate (%)')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('wallet_balance')
                    ->label('Wallet')
                    ->formatStateUsing(fn ($state) => '৳' . number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('customers_count')
                    ->label('Customers')
                    ->counts('customers')
                    ->sortable(),
                TextColumn::make('created_at')->dateTime('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(array_combine(
                        array_map(fn ($s) => $s->value, ResellerStatus::cases()),
                        array_map(fn ($s) => $s->label(), ResellerStatus::cases())
                    )),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Reseller $record) => $record->status !== ResellerStatus::SUSPENDED)
                    ->action(function (Reseller $record) {
                        $record->update(['status' => ResellerStatus::SUSPENDED, 'suspended_at' => now()]);
                        activity()->performedOn($record)->log('Reseller suspended');
                        Notification::make()->title('Reseller suspended')->warning()->send();
                    }),
                Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Reseller $record) => $record->status !== ResellerStatus::ACTIVE)
                    ->action(function (Reseller $record) {
                        $record->update(['status' => ResellerStatus::ACTIVE, 'activated_at' => now(), 'suspended_at' => null]);
                        activity()->performedOn($record)->log('Reseller reactivated');
                        Notification::make()->title('Reseller reactivated')->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResellers::route('/'),
            'create' => Pages\CreateReseller::route('/create'),
            'view' => Pages\ViewReseller::route('/{record}'),
            'edit' => Pages\EditReseller::route('/{record}/edit'),
        ];
    }
}
