<?php

namespace App\Filament\Resources\NetworkDevices;

use App\Filament\Resources\NetworkDevices\Pages\CreateNetworkDevice;
use App\Filament\Resources\NetworkDevices\Pages\EditNetworkDevice;
use App\Filament\Resources\NetworkDevices\Pages\ListNetworkDevices;
use App\Filament\Resources\NetworkDevices\Pages\ViewNetworkDevice;
use App\Filament\Resources\NetworkDevices\Schemas\NetworkDeviceForm;
use App\Filament\Resources\NetworkDevices\Schemas\NetworkDeviceInfolist;
use App\Filament\Resources\NetworkDevices\Tables\NetworkDevicesTable;
use App\Models\NetworkDevice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class NetworkDeviceResource extends Resource
{
    protected static ?string $model = NetworkDevice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-server';
    protected static ?string $navigationLabel = 'Network Devices';
    protected static string|\UnitEnum|null $navigationGroup = 'Network';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    public static function getPluralLabel(): string
    {
        return 'Network Devices';
    }

    public static function getSingularLabel(): string
    {
        return 'Network Device';
    }

    public static function form(Schema $schema): Schema
    {
        return NetworkDeviceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NetworkDeviceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NetworkDevicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListNetworkDevices::route('/'),
            'create' => CreateNetworkDevice::route('/create'),
            'view'   => ViewNetworkDevice::route('/{record}'),
            'edit'   => EditNetworkDevice::route('/{record}/edit'),
        ];
    }
}
