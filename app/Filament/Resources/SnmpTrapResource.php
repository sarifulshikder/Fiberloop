<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SnmpTrap\Pages\CreateSnmpTrap;
use App\Filament\Resources\SnmpTrap\Pages\EditSnmpTrap;
use App\Filament\Resources\SnmpTrap\Pages\ListSnmpTraps;
use App\Filament\Resources\SnmpTrap\Pages\ViewSnmpTrap;
use App\Filament\Resources\SnmpTrap\Schemas\SnmpTrapForm;
use App\Filament\Resources\SnmpTrap\Schemas\SnmpTrapInfolist;
use App\Filament\Resources\SnmpTrap\Tables\SnmpTrapsTable;
use App\Models\SnmpTrap;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SnmpTrapResource extends Resource
{
    protected static ?string $model = SnmpTrap::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|\UnitEnum|null $navigationGroup = 'Network Management';

    protected static ?string $navigationLabel = 'SNMP Traps';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return 'SNMP Trap';
    }

    public static function getPluralModelLabel(): string
    {
        return 'SNMP Traps';
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return SnmpTrapForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SnmpTrapInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SnmpTrapsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSnmpTraps::route('/'),
            'create' => CreateSnmpTrap::route('/create'),
            'view' => ViewSnmpTrap::route('/{record}'),
            'edit' => EditSnmpTrap::route('/{record}/edit'),
        ];
    }

    public static function getRoutePrefix(\Filament\Panel $panel): string
    {
        return 'snmp-traps';
    }
}
