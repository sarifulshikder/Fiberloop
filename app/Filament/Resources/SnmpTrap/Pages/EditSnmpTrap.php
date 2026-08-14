<?php

namespace App\Filament\Resources\SnmpTrap\Pages;

use App\Filament\Resources\SnmpTrapResource;
use Filament\Resources\Pages\EditRecord;

class EditSnmpTrap extends EditRecord
{
    protected static string $resource = SnmpTrapResource::class;

    protected function beforeFill(): void
    {
        $this->data['updated_by'] = auth()->id();
    }

    protected function beforeSave(): void
    {
        $this->record->updated_by = auth()->id();
    }
}
