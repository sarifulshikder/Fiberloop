<?php

namespace App\Filament\Resources\OltResource\Pages;

use App\Filament\Resources\OltResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOlts extends ListRecords
{
    protected static string $resource = OltResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('syncAll')
                ->label('Sync All OLTs')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function () {
                    set_time_limit(300); // 5 minutes for syncing all OLTs
                    $olts = \App\Models\Olt::active()->get();
                    $successCount = 0;
                    foreach ($olts as $olt) {
                        $result = app(\App\Services\Network\OltSyncService::class)->sync($olt);
                        if ($result['reachable'] ?? false) {
                            $successCount++;
                        }
                    }
                    \Filament\Notifications\Notification::make()
                        ->title("Synced $successCount OLT(s)")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
