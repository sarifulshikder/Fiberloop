<?php

namespace App\Jobs;

use App\Events\Billing\InvoiceGenerated;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Subscription;
use App\Services\Billing\InvoiceNumberGenerator;
use App\Services\Billing\ProrationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job to generate an invoice for a subscription.
 * This is queued per subscription for parallel processing and fault isolation.
 * The job is idempotent - running it twice for the same period won't create duplicates.
 */
class GenerateInvoices implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $subscriptionId;
    public Carbon $cycleStart;
    public Carbon $cycleEnd;

    public function __construct(int $subscriptionId, Carbon $cycleStart, Carbon $cycleEnd)
    {
        $this->subscriptionId = $subscriptionId;
        $this->cycleStart = $cycleStart;
        $this->cycleEnd = $cycleEnd;
    }

    public function handle(
        InvoiceNumberGenerator $invoiceNumberGenerator,
        ProrationService $prorationService
    ): void {
        $subscription = Subscription::with(['customer', 'package'])->find($this->subscriptionId);

        if (!$subscription) {
            Log::warning("GenerateInvoices: Subscription {$this->subscriptionId} not found");
            return;
        }

        // Skip if subscription is not active
        if (!$subscription->is_active) {
            Log::debug("GenerateInvoices: Subscription {$this->subscriptionId} is not active, skipping");
            return;
        }

        // Check for existing invoice (idempotency)
        $existingInvoice = Invoice::where('subscription_id', $subscription->id)
            ->where('period_start', $this->cycleStart->toDateString())
            ->where('period_end', $this->cycleEnd->toDateString())
            ->first();

        if ($existingInvoice) {
            Log::debug("GenerateInvoices: Invoice already exists for subscription {$this->subscriptionId} period {$this->cycleStart->toDateString()} to {$this->cycleEnd->toDateString()}");
            return;
        }

        DB::transaction(function () use ($subscription, $invoiceNumberGenerator, $prorationService) {
            $tenantId = $subscription->customer->tenant_id ?? null;
            $package = $subscription->package;
            $customer = $subscription->customer;

            // Calculate base amounts
            $packagePrice = $package->price;
            $taxRate = config('billing.tax_rate', 15); // Default 15% VAT for Bangladesh

            // Check if this is a prorated invoice (mid-cycle activation or change)
            $isProrated = false;
            $prorationAmount = 0;
            $prorationNotes = null;

            // Mid-cycle activation proration
            if ($subscription->start_date > $this->cycleStart->toDateString()) {
                $isProrated = true;
                $activationDate = Carbon::parse($subscription->start_date);
                $prorationAmount = $prorationService->calculateActivationProration(
                    $packagePrice,
                    $activationDate,
                    $this->cycleStart->copy(),
                    $this->cycleEnd->copy()
                );
                $prorationNotes = "Mid-cycle activation proration";
            }

            // Package change proration (if subscription has proration flag set)
            if ($subscription->is_prorated && $subscription->proration_amount > 0) {
                $isProrated = true;
                $prorationAmount = $subscription->proration_amount;
                $prorationNotes = $subscription->proration_notes ?? 'Package change proration';
            }

            // Calculate amounts
            $baseAmount = $isProrated ? $prorationAmount : $packagePrice;
            $taxAmount = (int) round($baseAmount * $taxRate / 100);
            $subtotal = $baseAmount;
            $total = $subtotal + $taxAmount;

            // Generate invoice number (thread-safe via InvoiceNumberGenerator)
            $invoiceNumber = $invoiceNumberGenerator->generateInvoiceNumber($tenantId ?? 1);

            // Create invoice
            $invoice = Invoice::create([
                'tenant_id' => $tenantId,
                'uuid' => (string) \Str::orderedUuid(),
                'customer_id' => $subscription->customer_id,
                'subscription_id' => $subscription->id,
                'reseller_id' => $subscription->reseller_id,
                'invoice_number' => $invoiceNumber,
                'period_start' => $this->cycleStart->toDateString(),
                'period_end' => $this->cycleEnd->toDateString(),
                'due_date' => $this->calculateDueDate($package, $this->cycleEnd),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'tax_rate' => $taxRate,
                'discount_amount' => 0, // Discounts handled separately if needed
                'total' => $total,
                'paid_amount' => 0,
                'outstanding_amount' => $total,
                'status' => 'draft',
                'notes' => $prorationNotes,
                'is_prorated' => $isProrated,
                'proration_amount' => $prorationAmount,
                'created_by' => 1, // System user
                'updated_by' => 1,
            ]);

            // Create invoice item
            $description = $isProrated
                ? "{$package->name} - Prorated ({$this->cycleStart->toDateString()} to {$this->cycleEnd->toDateString()})"
                : "{$package->name} - {$this->cycleStart->toDateString()} to {$this->cycleEnd->toDateString()}";

            InvoiceItem::create([
                'tenant_id' => $tenantId,
                'invoice_id' => $invoice->id,
                'description' => $description,
                'item_type' => 'service',
                'quantity' => 1,
                'unit_price' => $baseAmount,
                'amount' => $baseAmount,
                'period_start' => $this->cycleStart->toDateString(),
                'period_end' => $this->cycleEnd->toDateString(),
                'metadata' => [
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'package_price' => $packagePrice,
                    'is_prorated' => $isProrated,
                    'proration_amount' => $prorationAmount,
                    'tax_rate' => $taxRate,
                ],
            ]);

            // Update subscription next billing date
            $nextBillingDate = $this->calculateNextBillingDate($subscription, $this->cycleEnd);
            $subscription->update([
                'next_billing_date' => $nextBillingDate,
            ]);

            // Fire InvoiceGenerated event
            event(new InvoiceGenerated($invoice));

            Log::info("Invoice generated", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoiceNumber,
                'subscription_id' => $subscription->id,
                'customer_id' => $customer->id,
                'amount' => $total,
                'is_prorated' => $isProrated,
                'proration_amount' => $prorationAmount,
            ]);
        });
    }

    /**
     * Calculate due date based on package billing cycle.
     */
    protected function calculateDueDate($package, Carbon $cycleEnd): Carbon
    {
        $billingCycle = $package->billing_cycle ?? 'monthly';
        $graceDays = config('billing.grace_period_days', 5);

        return match ($billingCycle) {
            'monthly' => $cycleEnd->copy()->addDays($graceDays),
            'quarterly' => $cycleEnd->copy()->addDays(7),
            'yearly' => $cycleEnd->copy()->addDays(15),
            default => $cycleEnd->copy()->addDays($graceDays),
        };
    }

    /**
     * Calculate next billing date for subscription.
     */
    protected function calculateNextBillingDate(Subscription $subscription, Carbon $cycleEnd): string
    {
        $package = $subscription->package;
        $billingCycle = $package->billing_cycle ?? 'monthly';

        $nextDate = match ($billingCycle) {
            'monthly' => $cycleEnd->copy()->addMonth()->startOfMonth(),
            'quarterly' => $cycleEnd->copy()->addQuarter()->firstOfQuarter(),
            'yearly' => $cycleEnd->copy()->addYear()->startOfYear(),
            default => $cycleEnd->copy()->addMonth()->startOfMonth(),
        };

        return $nextDate->toDateString();
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("GenerateInvoices job failed", [
            'subscription_id' => $this->subscriptionId,
            'cycle_start' => $this->cycleStart->toDateString(),
            'cycle_end' => $this->cycleEnd->toDateString(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
