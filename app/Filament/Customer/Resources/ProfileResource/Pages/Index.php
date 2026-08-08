<?php

namespace App\Filament\Customer\Resources\ProfileResource\Pages;

use App\Filament\Customer\Resources\ProfileResource;
use Filament\Resources\Pages\ListRecords;

class Index extends ListRecords
{
    protected static string $resource = ProfileResource::class;

    public function mount(int|string|null $record = null): void
    {
        // Accept optional record parameter to prevent dependency resolution issues
    }

    protected function getHeaderActions(): array
    {
        return [
            // No create action for profile
        ];
    }
}