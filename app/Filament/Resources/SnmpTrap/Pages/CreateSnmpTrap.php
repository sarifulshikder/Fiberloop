<?php

namespace App\Filament\Resources\SnmpTrap\Pages;

use App\Filament\Resources\SnmpTrapResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSnmpTrap extends CreateRecord
{
    protected static string $resource = SnmpTrapResource::class;

    protected function beforeFill(): void
    {
        $this->data['tenant_id'] = auth()->user()?->tenant_id;
        $this->data['created_by'] = auth()->id();
        $this->data['updated_by'] = auth()->id();
    }

    protected function beforeSave(): void
    {
        $this->record->tenant_id = auth()->user()?->tenant_id;
        $this->record->created_by = auth()->id();
        $this->record->updated_by = auth()->id();
    }

    protected function afterSave(): void
    {
        $this->record->refresh();
    }
}
