<?php

namespace App\Listeners\Billing;

use App\Events\Billing\PaymentReceived;
use App\Events\Billing\SubscriptionReactivated;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Listener that automatically reactivates suspended subscriptions when payment is received.
 * Checks if the payment clears all outstanding invoices for the customer's subscriptions.
 * Fires SubscriptionReactivated event for Phase 7 (FreeRADIUS) to consume.
 */
class AutoReactivateOnPayment implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PaymentReceived $event): void
    {
        $payment = $event->payment;
        $customer = $payment->customer;
        
        // Only process completed payments
        if ($payment->status !== 'completed') {
            Log::debug("AutoReactivate: Payment not completed, skipping", [
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);
            return;
        }
        
        // Get all suspended subscriptions for this customer
        $suspendedSubscriptions = Subscription::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'suspended')
            ->whereNotNull('suspended_at')
            ->with(['invoices'])
            ->get();
        
        if ($suspendedSubscriptions->isEmpty()) {
            Log::debug("AutoReactivate: No suspended subscriptions for customer", [
                'customer_id' => $customer->id,
            ]);
            return;
        }
        
        // Check if payment clears the outstanding balance
        foreach ($suspendedSubscriptions as $subscription) {
            if ($this->shouldReactivate($subscription, $payment)) {
                $this->reactivateSubscription($subscription, $payment);
            }
        }
    }

    /**
     * Determine if a suspended subscription should be reactivated.
     */
    protected function shouldReactivate(Subscription $subscription, $payment): bool
    {
        // Get total outstanding for this subscription's invoices
        $outstandingTotal = Invoice::query()
            ->where('customer_id', $subscription->customer_id)
            ->where('subscription_id', $subscription->id)
            ->whereIn('status', ['draft', 'sent', 'overdue', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->sum('outstanding_amount');
        
        // Check if payment covers the outstanding (considering payment amount and any existing paid amount)
        $totalPaidForSubscription = Invoice::query()
            ->where('customer_id', $subscription->customer_id)
            ->where('subscription_id', $subscription->id)
            ->sum('paid_amount');
        
        // Payment is for specific invoice - check if that invoice's outstanding is cleared
        if ($payment->invoice_id) {
            $invoice = Invoice::find($payment->invoice_id);
            if ($invoice && $invoice->subscription_id === $subscription->id) {
                // Check if this invoice is now fully paid
                return $invoice->outstanding_amount <= 0;
            }
        }
        
        // For customer-level payments, check if all subscriptions have zero outstanding
        return $outstandingTotal <= 0;
    }

    /**
     * Reactivate a suspended subscription.
     */
    protected function reactivateSubscription(Subscription $subscription, $payment): void
    {
        DB::transaction(function () use ($subscription, $payment) {
            $customer = $subscription->customer;
            
            // Update subscription status
            $subscription->update([
                'status' => 'active',
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);
            
            // Update customer status if they have at least one active subscription
            $this->updateCustomerStatus($customer);
            
            // Fire SubscriptionReactivated event for Phase 7
            event(new SubscriptionReactivated(
                $customer,
                $payment,
                'Payment received - auto-reactivated'
            ));
            
            Log::info("Subscription auto-reactivated", [
                'subscription_id' => $subscription->id,
                'customer_id' => $customer->id,
                'payment_id' => $payment->id,
                'payment_amount' => $payment->amount,
            ]);
            
            // Activity log for financial audit
            activity()
                ->by(1) // System user
                ->on($subscription)
                ->withProperties([
                    'action' => 'subscription_reactivated',
                    'reason' => 'Payment received - auto-reactivated',
                    'payment_id' => $payment->id,
                    'payment_amount' => $payment->amount,
                ])
                ->log('Auto-reactivated subscription after payment');
        });
    }

    /**
     * Update customer status based on their subscriptions.
     */
    protected function updateCustomerStatus($customer): void
    {
        // Check if customer has at least one active subscription
        $activeSubscriptions = Subscription::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->count();
        
        if ($activeSubscriptions > 0) {
            $customer->update([
                'status' => 'active',
                'suspended_at' => null,
            ]);
        }
    }

    /**
     * Handle listener failure.
     */
    public function failed(PaymentReceived $event, $exception): void
    {
        Log::error("AutoReactivateOnPayment listener failed", [
            'payment_id' => $event->payment->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
