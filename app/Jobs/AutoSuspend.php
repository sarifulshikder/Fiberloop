<?php

namespace App\Jobs;

use App\Events\Billing\SubscriptionSuspended;
use App\Models\Invoice;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job to automatically suspend subscriptions for customers with overdue invoices.
 * Checks if customer has passed the grace period and has outstanding balance.
 * Fires SubscriptionSuspended event for Phase 7 (FreeRADIUS) to consume.
 */
class AutoSuspend implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $gracePeriodDays = config('billing.grace_period_days', 5);
        $today = Carbon::today();

        // Find subscriptions where:
        // 1. Customer has overdue invoices past grace period
        // 2. Subscription is currently active
        // 3. Not already suspended

        $subscriptionsToSuspend = Subscription::query()
            ->with(['customer', 'invoices'])
            ->where('status', 'active')
            ->whereNull('suspended_at')
            ->whereHas('invoices', function ($query) use ($today, $gracePeriodDays) {
                $query->whereIn('status', ['sent', 'overdue', 'partial'])
                    ->where('due_date', '<', $today->subDays($gracePeriodDays)->toDateString())
                    ->where('outstanding_amount', '>', 0);
            })
            ->get();

        $suspendedCount = 0;

        foreach ($subscriptionsToSuspend as $subscription) {
            // Check if this subscription should be suspended
            if ($this->shouldSuspend($subscription, $gracePeriodDays)) {
                $this->suspendSubscription($subscription);
                $suspendedCount++;
            }
        }

        Log::info("Auto-suspend job completed", [
            'subscriptions_checked' => $subscriptionsToSuspend->count(),
            'subscriptions_suspended' => $suspendedCount,
        ]);
    }

    /**
     * Determine if a subscription should be suspended.
     */
    protected function shouldSuspend(Subscription $subscription, int $gracePeriodDays): bool
    {
        // Skip if already suspended
        if ($subscription->status !== 'active') {
            return false;
        }

        // Check for any overdue invoices past grace period
        $gracePeriodEnd = Carbon::today()->subDays($gracePeriodDays);

        return Invoice::query()
            ->where('customer_id', $subscription->customer_id)
            ->where('subscription_id', $subscription->id)
            ->whereIn('status', ['sent', 'overdue', 'partial'])
            ->where('due_date', '<', $gracePeriodEnd->toDateString())
            ->where('outstanding_amount', '>', 0)
            ->exists();
    }

    /**
     * Suspend a subscription and fire event.
     */
    protected function suspendSubscription(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $customer = $subscription->customer;

            // Find the most overdue invoice for reference
            $overdueInvoice = Invoice::query()
                ->where('customer_id', $subscription->customer_id)
                ->where('subscription_id', $subscription->id)
                ->whereIn('status', ['sent', 'overdue', 'partial'])
                ->where('outstanding_amount', '>', 0)
                ->orderBy('due_date')
                ->first();

            if (!$overdueInvoice) {
                return;
            }

            // Update subscription status
            $subscription->update([
                'status' => 'suspended',
                'suspended_at' => now(),
                'suspension_reason' => 'Non-payment of invoice ' . ($overdueInvoice->invoice_number ?? $overdueInvoice->id),
            ]);

            // Update customer status if all their subscriptions are now suspended
            $this->updateCustomerStatus($customer);

            // Fire SubscriptionSuspended event for Phase 7
            event(new SubscriptionSuspended(
                $customer,
                $overdueInvoice,
                'Non-payment of invoice'
            ));

            Log::info("Subscription suspended", [
                'subscription_id' => $subscription->id,
                'customer_id' => $customer->id,
                'invoice_id' => $overdueInvoice->id,
                'invoice_number' => $overdueInvoice->invoice_number,
                'outstanding_amount' => $overdueInvoice->outstanding_amount,
            ]);

            // Activity log for financial audit
            activity()
                ->by(1) // System user
                ->on($subscription)
                ->withProperties([
                    'action' => 'subscription_suspended',
                    'reason' => 'Non-payment of invoice ' . ($overdueInvoice->invoice_number ?? $overdueInvoice->id),
                    'invoice_id' => $overdueInvoice->id,
                    'outstanding_amount' => $overdueInvoice->outstanding_amount,
                ])
                ->log('Suspended subscription for non-payment');
        });
    }

    /**
     * Update customer status based on their subscriptions.
     */
    protected function updateCustomerStatus($customer): void
    {
        // Check if customer has any active subscriptions left
        $activeSubscriptions = Subscription::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->count();

        if ($activeSubscriptions === 0) {
            $customer->update([
                'status' => 'suspended',
                'suspended_at' => now(),
            ]);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("AutoSuspend job failed", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
