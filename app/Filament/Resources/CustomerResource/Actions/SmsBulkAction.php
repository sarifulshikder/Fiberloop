<?php

namespace App\Filament\Resources\CustomerResource\Actions;

use App\Models\Customer;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Illuminate\Database\Eloquent\Collection;

class SmsBulkAction extends BulkAction
{
    use CanCustomizeProcess;

    public static function getDefaultName(): ?string
    {
        return 'sms';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Send SMS');
        $this->modalHeading('Send SMS to Selected Customers');
        $this->modalSubmitActionLabel('Send');
        $this->successNotificationTitle('SMS sent');
        $this->failureNotificationTitle('Failed to send some SMS');
        $this->icon('heroicon-o-paper-airplane');
        $this->color('primary');
    }

    public function handle(Collection $records, array $data): void
    {
        $message = $data['message'] ?? '';
        $actor = auth()->user();

        foreach ($records as $record) {
            try {
                // TODO: Integrate with SMS gateway
                // This is a placeholder for the actual SMS sending logic
                // In Phase 11 (Notifications), we'll implement the full SMS service
                activity()
                    ->by($actor)
                    ->on($record)
                    ->withProperties(['message' => $message, 'type' => 'sms'])
                    ->log('SMS sent to customer');

                // Log to notifications_log
                $record->notifications()->create([
                    'type' => 'sms',
                    'content' => $message,
                    'sent_by' => $actor->id,
                    'status' => 'sent',
                ]);
            } catch (\Exception $e) {
                activity()
                    ->by($actor)
                    ->on($record)
                    ->withProperties(['error' => $e->getMessage(), 'type' => 'sms'])
                    ->log('Failed to send SMS');
            }
        }

        $this->success();
    }

    public function getFormSchema(): array
    {
        return [
            \Filament\Forms\Components\Textarea::make('message')
                ->label('SMS Message')
                ->required()
                ->maxLength(500)
                ->rows(5)
                ->hint('Max 500 characters. Standard SMS limit is 160 characters.'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('send_sms', Customer::class) ?? false;
    }
}
