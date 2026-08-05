<?php

namespace App\Services\Billing;

use App\Jobs\GenerateInvoices;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for orchestrating billing runs.
 * Generates invoices for all active subscriptions whose billing cycle is due.
 * Each subscription gets its own queued job for parallel processing and fault isolation.
 */
class BillingRunService
{
    protected ProrationService $prorationService;
    protected InvoiceNumberGenerator $invoiceNumberGenerator;

    public function __construct(
        ProrationService $prorationService,
        InvoiceNumberGenerator $invoiceNumberGenerator
    ) {
        $this->prorationService = $prorationService;
        $this->invoiceNumberGenerator = $invoiceNumberGenerator;
    }

    /**
     * Run billing for all subscriptions due for the current cycle.
     * Returns stats about the run.
     */
    public function runBillingForDueSubscriptions(Carbon $cycleStart, Carbon $cycleEnd): array
    {
        $subscriptions = $this->getSubscriptionsDueForBilling($cycleStart, $cycleEnd);
        
        $totalSubscriptions = $subscriptions->count();
        $jobsDispatched = 0;
        $alreadyInvoiced = 0;
        
        foreach ($subscriptions as $subscription) {
            // Check if invoice already exists for this period (idempotency)
            if ($this->invoiceAlreadyExistsForPeriod($subscription, $cycleStart, $cycleEnd)) {
                $alreadyInvoiced++;
                continue;
            }
            
            // Dispatch queued job for this subscription
            GenerateInvoices::dispatch(
                $subscription->id,
                $cycleStart->copy(),
                $cycleEnd->copy()
            );
            
            $jobsDispatched++;
        }
        
        Log::info("Billing run completed", [
            'cycle_start' => $cycleStart->toDateString(),
            'cycle_end' => $cycleEnd->toDateString(),
            'total_subscriptions_due' => $totalSubscriptions,
            'jobs_dispatched' => $jobsDispatched,
            'already_invoiced' => $alreadyInvoiced,
        ]);
        
        return [
            'cycle_start' => $cycleStart->toDateString(),
            'cycle_end' => $cycleEnd->toDateString(),
            'total_subscriptions_due' => $totalSubscriptions,
            'jobs_dispatched' => $jobsDispatched,
            'already_invoiced' => $alreadyInvoiced,
        ];
    }

    /**
     * Get subscriptions that are due for billing in the given period.
     */
    protected function getSubscriptionsDueForBilling(Carbon $cycleStart, Carbon $cycleEnd): \Illuminate\Database\Eloquent\Collection
    {
        return Subscription::query()
            ->with(['customer', 'package'])
            ->whereIn('status', ['active', 'pending'])
            ->where('next_billing_date', '<=', $cycleEnd->toDateString())
            ->where(function ($query) use ($cycleStart, $cycleEnd) {
                // Subscriptions that started before or on cycle end
                // and don't have an invoice for this period yet
                $query->where('start_date', '<=', $cycleEnd->toDateString())
                    ->where(function ($q) use ($cycleStart) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', $cycleStart->toDateString());
                    });
            })
            ->orderBy('next_billing_date')
            ->get();
    }

    /**
     * Check if an invoice already exists for this subscription and period.
     */
    protected function invoiceAlreadyExistsForPeriod(
        Subscription $subscription,
        Carbon $cycleStart,
        Carbon $cycleEnd
    ): bool {
        return DB::table('invoices')
            ->where('subscription_id', $subscription->id)
            ->where('period_start', $cycleStart->toDateString())
            ->where('period_end', $cycleEnd->toDateString())
            ->exists();
    }

    /**
     * Run billing for a specific subscription (synchronous, for testing).
     */
    public function runBillingForSubscription(
        Subscription $subscription,
        Carbon $cycleStart,
        Carbon $cycleEnd
    ): bool {
        if ($this->invoiceAlreadyExistsForPeriod($subscription, $cycleStart, $cycleEnd)) {
            return false;
        }
        
        // Run synchronously for testing
        $job = new GenerateInvoices($subscription->id, $cycleStart, $cycleEnd);
        $job->handle();
        
        return true;
    }

    /**
     * Get subscriptions due for billing today.
     */
    public function getTodayDueSubscriptions(): \Illuminate\Database\Eloquent\Collection
    {
        $today = Carbon::today();
        $cycleStart = $today->copy()->startOfMonth();
        $cycleEnd = $today->copy()->endOfMonth();
        
        // For monthly billing, check subscriptions with next_billing_date <= today
        return Subscription::query()
            ->with(['customer', 'package'])
            ->whereIn('status', ['active'])
            ->where('next_billing_date', '<=', $today->toDateString())
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today->toDateString());
            })
            ->get();
    }

    /**
     * Calculate the billing cycle dates for a subscription.
     */
    public function calculateBillingCycle(Subscription $subscription, Carbon $asOf = null): array
    {
        $asOf = $asOf ?? now();
        $package = $subscription->package;
        $billingCycle = $package->billing_cycle ?? 'monthly';
        
        $cycleStart = match ($billingCycle) {
            'monthly' => $asOf->copy()->startOfMonth(),
            'quarterly' => $asOf->copy()->firstOfQuarter(),
            'yearly' => $asOf->copy()->startOfYear(),
            default => $asOf->copy()->startOfMonth(),
        };
        
        $cycleEnd = match ($billingCycle) {
            'monthly' => $asOf->copy()->endOfMonth(),
            'quarterly' => $asOf->copy()->lastOfQuarter(),
            'yearly' => $asOf->copy()->endOfYear(),
            default => $asOf->copy()->endOfMonth(),
        };
        
        return [
            'start' => $cycleStart,
            'end' => $cycleEnd,
            'billing_cycle' => $billingCycle,
        ];
    }
}
