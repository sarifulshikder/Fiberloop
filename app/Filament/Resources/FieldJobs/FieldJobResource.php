<?php

namespace App\Filament\Resources\FieldJobs;

use App\Filament\Resources\FieldJobs\Pages\CreateFieldJob;
use App\Filament\Resources\FieldJobs\Pages\EditFieldJob;
use App\Filament\Resources\FieldJobs\Pages\ListFieldJobs;
use App\Filament\Resources\FieldJobs\Schemas\FieldJobForm;
use App\Filament\Resources\FieldJobs\Tables\FieldJobsTable;
use App\Models\FieldJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FieldJobResource extends Resource
{
    protected static ?string $model = FieldJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FieldJobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FieldJobsTable::configure($table);
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
            'index' => ListFieldJobs::route('/'),
            'create' => CreateFieldJob::route('/create'),
            'edit' => EditFieldJob::route('/{record}/edit'),
        ];
    }
}
