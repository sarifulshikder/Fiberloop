<?php

namespace App\Filament\Resources\CustomerResource\Actions;

use App\Models\Customer;
use App\Services\CustomerStatusManager;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Illuminate\Database\Eloquent\Collection;

class SuspendBulkAction extends BulkAction
{
    use CanCustomizeProcess;

    public static function getDefaultName(): ?string
    {
        return 'suspend';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Suspend');
        $this->modalHeading('Suspend Selected Customers');
        $this->modalSubmitActionLabel('Suspend');
        $this->successNotificationTitle('Customers suspended');
        $this->failureNotificationTitle('Failed to suspend some customers');
        $this->icon('heroicon-o-pause');
        $this->color('warning');
    }

    public function handle(Collection $records, array $data): void
    {
        $actor = auth()->user();
        $reason = $data['reason'] ?? 'Bulk suspension';
        
        foreach ($records as $record) {
            try {
                app(CustomerStatusManager::class)->suspend($record, $actor, $reason);
            } catch (\Exception $e) {
                // Log error but continue with other records
                activity()
                    ->by($actor)
                    ->on($record)
                    ->withProperties(['error' => $e->getMessage()])
                    ->log('Failed to suspend customer in bulk');
            }
        }

        $this->success();
    }

    public function getFormSchema(): array
    {
        return [
            \Filament\Forms\Components\TextInput::make('reason')
                ->label('Suspension Reason')
                ->required()
                ->maxLength(500),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('suspend', Customer::class) ?? false;
    }
}
