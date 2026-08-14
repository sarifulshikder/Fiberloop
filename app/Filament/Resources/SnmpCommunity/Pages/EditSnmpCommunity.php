<?php

namespace App\Filament\Resources\SnmpCommunity\Pages;

use App\Filament\Resources\SnmpCommunityResource;
use Filament\Resources\Pages\EditRecord;

class EditSnmpCommunity extends EditRecord
{
    protected static string $resource = SnmpCommunityResource::class;

    protected function beforeFill(): void
    {
        $this->data['updated_by'] = auth()->id();
    }

    protected function beforeSave(): void
    {
        $this->record->updated_by = auth()->id();
    }
}
