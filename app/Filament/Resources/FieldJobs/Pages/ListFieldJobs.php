<?php

namespace App\Filament\Resources\FieldJobs\Pages;

use App\Filament\Resources\FieldJobs\FieldJobResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFieldJobs extends ListRecords
{
    protected static string $resource = FieldJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
