<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OltResource\Pages\CreateOlt;
use App\Filament\Resources\OltResource\Pages\EditOlt;
use App\Filament\Resources\OltResource\Pages\ListOlts;
use App\Models\NetworkDevice;
use App\Models\Olt;
use App\Services\Network\OltPortPollService;
use App\Services\Network\OltSyncService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OltResource extends Resource
{
    protected static ?string $model = Olt::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';
    protected static string|\UnitEnum|null $navigationGroup = 'Network';
    protected static ?string $navigationLabel = 'OLTs';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('OLT Details')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    \Filament\Forms\Components\Select::make('network_device_id')
                        ->label('Network Device')
                        ->options(NetworkDevice::pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('chassis_id')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('firmware_version')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('hardware_version')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('total_pon_ports')
                        ->numeric()
                        ->default(0),
                    \Filament\Forms\Components\TextInput::make('max_onus_per_pon')
                        ->numeric()
                        ->default(64),
                    \Filament\Forms\Components\TextInput::make('rack')
                        ->maxLength(255),
                    \Filament\Forms\Components\TextInput::make('slot')
                        ->maxLength(255),
                    \Filament\Forms\Components\Textarea::make('location_notes')
                        ->rows(2),
                    \Filament\Forms\Components\Toggle::make('is_active')
                        ->default(true),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->rows(3),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('networkDevice.name')
                    ->label('Network Device')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_pon_ports')
                    ->label('PON Ports')
                    ->sortable(),
                TextColumn::make('used_pon_ports')
                    ->label('Used Ports')
                    ->sortable(),
                TextColumn::make('firmware_version')
                    ->label('Firmware')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('last_sync_at')
                    ->label('Last Sync')
                    ->dateTime()
                    ->sortable(),
            ])
            ->query(Olt::query()->with('networkDevice'))
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->actions([
                Action::make('syncNow')
                    ->label('Sync Now')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->tooltip('Discover ONUs and refresh optical signals from this OLT')
                    ->action(function (Olt $record, $livewire): void {
                        // Increase PHP execution time for this operation
                        set_time_limit(120);

                        $result = app(OltSyncService::class)->sync($record);

                        if (!$result['reachable']) {
                            Notification::make()
                                ->title("{$record->name} is unreachable")
                                ->body('The OLT did not respond to discovery. Check the network device and try again.')
                                ->danger()
                                ->send();
                        } else {
                            Notification::make()
                                ->title("{$record->name} synced")
                                ->body(
                                    "Discovered {$result['discovered']} ONU(s) — "
                                    . "{$result['created']} created, {$result['updated']} updated. "
                                    . "Signal read for {$result['signal_ok']} ONU(s)."
                                )
                                ->success()
                                ->send();
                        }

                        $livewire->resetTable();
                    }),
                Action::make('pollPorts')
                    ->label('Poll Ports')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('info')
                    ->tooltip('Poll SFP/port details (DOM, status, counters) from this OLT')
                    ->action(function (Olt $record, $livewire): void {
                        set_time_limit(120);

                        $result = app(OltPortPollService::class)->poll($record);

                        if (!$result['reachable']) {
                            Notification::make()
                                ->title("{$record->name} is unreachable")
                                ->body('The OLT did not respond to port polling. Check the network device and try again.')
                                ->danger()
                                ->send();
                        } else {
                            Notification::make()
                                ->title("{$record->name} ports polled")
                                ->body(
                                    "Polled {$result['polled']} port(s) — "
                                    . "{$result['created']} created, {$result['updated']} updated."
                                )
                                ->success()
                                ->send();
                        }

                        $livewire->resetTable();
                    }),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('syncSelected')
                        ->label('Sync Selected')
                        ->icon('heroicon-o-arrow-path')
                        ->color('primary')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            set_time_limit(180); // 3 minutes for bulk sync
                            $successCount = 0;
                            foreach ($records as $record) {
                                $result = app(OltSyncService::class)->sync($record);
                                if ($result['reachable'] ?? false) {
                                    $successCount++;
                                }
                            }
                            Notification::make()
                                ->title("Synced $successCount OLT(s)")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListOlts::route('/'),
            'create' => CreateOlt::route('/create'),
            'edit'   => EditOlt::route('/{record}/edit'),
        ];
    }
}
