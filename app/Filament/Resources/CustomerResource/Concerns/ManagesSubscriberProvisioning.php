<?php

namespace App\Filament\Resources\CustomerResource\Concerns;

use App\Enums\SubscriptionStatus;
use App\Models\Package;
use App\Services\Network\SubscriberProvisioningService;
use Filament\Notifications\Notification;
use Throwable;

trait ManagesSubscriberProvisioning
{
    /**
     * Turn the selected package into an active Subscription record and
     * provision the subscriber through their chosen method when active.
     */
    protected function syncSubscriptionAndProvision(): void
    {
        $packageId = $this->data['package_id'] ?? null;

        if (!$packageId) {
            return;
        }

        $this->upsertActiveSubscription((int) $packageId);
        $this->provisionSubscriber();
    }

    protected function upsertActiveSubscription(int $packageId): void
    {
        $package = Package::find($packageId);

        if (!$package) {
            return;
        }

        $existing = $this->record->getActiveSubscription();

        if ($existing) {
            $existing->update(['package_id' => $packageId]);

            return;
        }

        $this->record->subscriptions()->create([
            'package_id' => $packageId,
            'start_date' => now()->toDateString(),
            'next_billing_date' => now()->addMonth()->toDateString(),
            'status' => SubscriptionStatus::ACTIVE->value,
            'monthly_price' => $package->price ?? 0,
            'final_price' => $package->price ?? 0,
            'activated_at' => now(),
            'created_by' => auth()->id(),
        ]);
    }

    public function provisionSubscriber(): bool
    {
        $method = $this->record->provisioning_method?->label() ?? 'RADIUS';

        try {
            app(SubscriberProvisioningService::class)->provision($this->record);

            activity()
                ->by(auth()->user())
                ->on($this->record)
                ->withProperties(['action' => 'provisioned', 'method' => $method])
                ->log("Subscriber provisioned via {$method}");

            return true;
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Provisioning failed')
                ->body($e->getMessage())
                ->send();

            return false;
        }
    }
}
