<?php

namespace App\Filament\Resources\FieldJobs\Pages;

use App\Filament\Resources\FieldJobs\FieldJobResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFieldJob extends EditRecord
{
    protected static string $resource = FieldJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
