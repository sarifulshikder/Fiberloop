<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OltPortResource\Pages\CreateOltPort;
use App\Filament\Resources\OltPortResource\Pages\EditOltPort;
use App\Filament\Resources\OltPortResource\Pages\ListOltPorts;
use App\Models\Olt;
use App\Models\OltPort;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OltPortResource extends Resource
{
    protected static ?string $model = OltPort::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static string|\UnitEnum|null $navigationGroup = 'Network';
    protected static ?string $navigationLabel = 'OLT Ports';
    protected static ?int $navigationSort = 4;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Port Details')
                ->schema([
                    Select::make('olt_id')
                        ->label('OLT')
                        ->options(Olt::pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    TextInput::make('name')
                        ->label('Port Name (ifDescr)')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('alias')
                        ->label('Alias (ifAlias)')
                        ->maxLength(255),
                    TextInput::make('if_index')
                        ->label('SNMP ifIndex')
                        ->numeric()
                        ->required(),
                    Select::make('type_label')
                        ->label('Port Type')
                        ->options([
                            'uplink' => 'Uplink',
                            'pon' => 'PON',
                            'access' => 'Access',
                            'mgmt' => 'Management',
                            'other' => 'Other',
                        ])
                        ->searchable(),
                    TextInput::make('speed')
                        ->label('Speed (bps)')
                        ->numeric(),
                    TextInput::make('high_speed')
                        ->label('High Speed (Mbps)')
                        ->numeric(),
                    TextInput::make('mtu')
                        ->label('MTU')
                        ->numeric(),
                    TextInput::make('mac_address')
                        ->label('MAC Address')
                        ->maxLength(17),
                    Toggle::make('is_uplink')
                        ->label('Uplink Port')
                        ->default(false),
                    Toggle::make('is_pon')
                        ->label('PON Port')
                        ->default(false),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])->columns(2),

            \Filament\Schemas\Components\Section::make('SFP / Transceiver')
                ->schema([
                    Toggle::make('sfp_present')
                        ->label('SFP Present')
                        ->default(false),
                    TextInput::make('sfp_vendor')
                        ->label('Vendor')
                        ->maxLength(255),
                    TextInput::make('sfp_part_number')
                        ->label('Part Number')
                        ->maxLength(255),
                    TextInput::make('sfp_serial_number')
                        ->label('Serial Number')
                        ->maxLength(255),
                    TextInput::make('sfp_revision')
                        ->label('Revision')
                        ->maxLength(255),
                    TextInput::make('sfp_date_code')
                        ->label('Date Code')
                        ->maxLength(255),
                    TextInput::make('sfp_connector_type')
                        ->label('Connector')
                        ->maxLength(50),
                    TextInput::make('sfp_wavelength')
                        ->label('Wavelength')
                        ->maxLength(50),
                    TextInput::make('sfp_distance')
                        ->label('Distance')
                        ->maxLength(50),
                    TextInput::make('sfp_standard')
                        ->label('Standard')
                        ->maxLength(100),
                ])->columns(3)->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sfp_rx_power_dbm', 'asc')
            ->columns([
                // Primary identification
                TextColumn::make('name')
                    ->label('Port Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->copyable(),
                TextColumn::make('alias')
                    ->label('Alias')
                    ->searchable()
                    ->placeholder('—')
                    ->wrap(),

                // Type badges
                BadgeColumn::make('type_label')
                    ->label('Type')
                    ->colors([
                        'primary' => 'uplink',
                        'success' => 'pon',
                        'gray' => 'access',
                        'warning' => 'mgmt',
                        'info' => 'other',
                    ])
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : '—'),

                // Status
                BadgeColumn::make('oper_status_label')
                    ->label('Oper Status')
                    ->colors([
                        'success' => 'Up',
                        'danger' => 'Down',
                        'warning' => 'Testing',
                        'gray' => 'Unknown',
                        'info' => 'Dormant',
                        'danger' => 'Not Present',
                        'warning' => 'Lower Layer Down',
                    ])
                    ->sortable(),

                BadgeColumn::make('admin_status_label')
                    ->label('Admin Status')
                    ->colors([
                        'success' => 'Up',
                        'danger' => 'Down',
                        'warning' => 'Testing',
                    ])
                    ->sortable(),

                // Speed
                TextColumn::make('speed_label')
                    ->label('Speed')
                    ->sortable()
                    ->alignCenter(),

                // SFP Info
                TextColumn::make('sfp_vendor')
                    ->label('SFP Vendor')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sfp_part_number')
                    ->label('Part Number')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sfp_serial_number')
                    ->label('Serial')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Optical Power (DOM) - Color coded like ONU page
                TextColumn::make('sfp_rx_power_dbm')
                    ->label('Rx Power (dBm)')
                    ->numeric(2)
                    ->sortable()
                    ->color(fn ($state) => $state === null ? null : ($state < -27 ? 'danger' : ($state < -24 ? 'warning' : 'success')))
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('sfp_tx_power_dbm')
                    ->label('Tx Power (dBm)')
                    ->numeric(2)
                    ->sortable()
                    ->color(fn ($state) => $state === null ? null : ($state > 3 || $state < -10 ? 'danger' : ($state > 2 || $state < -8 ? 'warning' : 'success')))
                    ->placeholder('—'),

                // Temperature & Voltage
                TextColumn::make('sfp_temperature_c')
                    ->label('Temp (°C)')
                    ->numeric(1)
                    ->sortable()
                    ->color(fn ($state) => $state === null ? null : ($state > 75 ? 'danger' : ($state > 65 ? 'warning' : 'success')))
                    ->placeholder('—')
                    ->suffix('°C'),
                TextColumn::make('sfp_voltage_v')
                    ->label('Voltage (V)')
                    ->numeric(3)
                    ->sortable()
                    ->color(fn ($state) => $state === null ? null : ($state < 3.0 || $state > 3.6 ? 'danger' : ($state < 3.1 || $state > 3.5 ? 'warning' : 'success')))
                    ->placeholder('—')
                    ->suffix('V'),

                // Tx Bias
                TextColumn::make('sfp_tx_bias_ma')
                    ->label('Tx Bias (mA)')
                    ->numeric(2)
                    ->sortable()
                    ->placeholder('—')
                    ->suffix('mA')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Alarms/Warnings
                IconColumn::make('has_alarms')
                    ->label('Alarms')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('has_warnings')
                    ->label('Warnings')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Uptime
                TextColumn::make('uptime_string')
                    ->label('Uptime')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // Classification toggles
                IconColumn::make('is_uplink')
                    ->label('Uplink')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_pon')
                    ->label('PON')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                // Last polled
                TextColumn::make('last_polled_at')
                    ->label('Last Polled')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),
            ])
            ->filters([
                SelectFilter::make('olt_id')
                    ->label('OLT')
                    ->options(Olt::pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('type_label')
                    ->label('Port Type')
                    ->options([
                        'uplink' => 'Uplink',
                        'pon' => 'PON',
                        'access' => 'Access',
                        'mgmt' => 'Management',
                        'other' => 'Other',
                    ]),
                SelectFilter::make('oper_status')
                    ->label('Oper Status')
                    ->options([
                        1 => 'Up',
                        2 => 'Down',
                        3 => 'Testing',
                        4 => 'Unknown',
                        5 => 'Dormant',
                        6 => 'Not Present',
                        7 => 'Lower Layer Down',
                    ]),
                SelectFilter::make('is_uplink')
                    ->label('Uplink')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ]),
                SelectFilter::make('is_pon')
                    ->label('PON')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ]),
                SelectFilter::make('sfp_present')
                    ->label('SFP Present')
                    ->options([
                        '1' => 'Yes',
                        '0' => 'No',
                    ]),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListOltPorts::route('/'),
            'create' => CreateOltPort::route('/create'),
            'edit'   => EditOltPort::route('/{record}/edit'),
        ];
    }
}
