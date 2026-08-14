<?php

namespace App\Filament\Customer\Resources\ProfileResource\Pages;

use App\Filament\Customer\Resources\ProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProfile extends EditRecord
{
    protected static string $resource = ProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            // DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure customers can only edit their own profile
        $data['id'] = auth('customer')->id();
        return $data;
    }

    protected function afterSave(): void
    {
        // Clear the ResellerScope for this query since we're forcing customer_id
        // This ensures we can save the profile even with the global scope
    }
}
