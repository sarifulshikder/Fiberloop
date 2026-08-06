<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling prepaid customer billing.
 * Prepaid customers must have positive wallet balance before service starts.
 * Balance is decremented on a usage-cycle basis rather than invoice-then-pay.
 */
class PrepaidService
{
    protected ProrationService $prorationService;

    public function __construct(ProrationService $prorationService)
    {
        $this->prorationService = $prorationService;
    }

    /**
     * Check if customer has sufficient balance for service.
     */
    public function hasSufficientBalance(Customer $customer, int $requiredAmount): bool
    {
        return $customer->wallet_balance >= $requiredAmount;
    }

    /**
     * Check if customer can activate service based on wallet balance.
     */
    public function canActivateService(Customer $customer, Subscription $subscription): bool
    {
        $packagePrice = $subscription->package->price;

        // For monthly packages, check if customer has at least one month's fee
        $billingCycle = $subscription->package->billing_cycle ?? 'monthly';

        $requiredAmount = match ($billingCycle) {
            'monthly' => $packagePrice,
            'quarterly' => $packagePrice * 3,
            'yearly' => $packagePrice * 12,
            default => $packagePrice,
        };

        return $this->hasSufficientBalance($customer, $requiredAmount);
    }

    /**
     * Deduct amount from customer's wallet balance.
     * All amounts are in poysha.
     */
    public function deductFromBalance(Customer $customer, int $amount, string $description, string $reference = null): bool
    {
        return DB::transaction(function () use ($customer, $amount, $description, $reference) {
            if (!$this->hasSufficientBalance($customer, $amount)) {
                Log::warning("Insufficient balance for deduction", [
                    'customer_id' => $customer->id,
                    'amount' => $amount,
                    'current_balance' => $customer->wallet_balance,
                    'description' => $description,
                ]);
                return false;
            }

            $newBalance = $customer->wallet_balance - $amount;
            $customer->update(['wallet_balance' => $newBalance]);

            // Record wallet transaction for audit
            \App\Models\WalletTransaction::recordDebit(
                $customer,
                $amount,
                $description,
                'subscription',
                null,
                1, // System user
                ['reference' => $reference]
            );

            Log::info("Wallet deduction", [
                'customer_id' => $customer->id,
                'amount' => $amount,
                'new_balance' => $newBalance,
                'description' => $description,
                'reference' => $reference,
            ]);

            return true;
        });
    }

    /**
     * Credit amount to customer's wallet balance.
     * All amounts are in poysha.
     */
    public function creditToBalance(Customer $customer, int $amount, string $description, string $reference = null): bool
    {
        return DB::transaction(function () use ($customer, $amount, $description, $reference) {
            $newBalance = $customer->wallet_balance + $amount;
            $customer->update(['wallet_balance' => $newBalance]);

            // Record wallet transaction for audit
            \App\Models\WalletTransaction::recordCredit(
                $customer,
                $amount,
                $description,
                'payment',
                null,
                1, // System user
                ['reference' => $reference]
            );

            Log::info("Wallet credit", [
                'customer_id' => $customer->id,
                'amount' => $amount,
                'new_balance' => $newBalance,
                'description' => $description,
                'reference' => $reference,
            ]);

            return true;
        });
    }

    /**
     * Process prepaid billing for active subscriptions.
     * Deducts usage-based charges from wallet balance.
     */
    public function processUsageBilling(Subscription $subscription, Carbon $periodStart, Carbon $periodEnd): bool
    {
        return DB::transaction(function () use ($subscription, $periodStart, $periodEnd) {
            $customer = $subscription->customer;
            $package = $subscription->package;

            if (!$customer || !$package) {
                return false;
            }

            // Calculate usage charge based on package
            $billingCycle = $package->billing_cycle ?? 'monthly';
            $packagePrice = $package->price;

            // Calculate prorated amount if not full cycle
            $totalDays = $periodStart->diffInDays($periodEnd) + 1;
            $daysInMonth = $periodStart->daysInMonth;

            // For simplicity, assume full cycle charge for now
            // In a real implementation, this would calculate actual usage
            $chargeAmount = $packagePrice;

            if (!$this->hasSufficientBalance($customer, $chargeAmount)) {
                // Suspend subscription if insufficient balance
                $this->suspendForInsufficientBalance($subscription);
                return false;
            }

            // Deduct from balance
            $success = $this->deductFromBalance(
                $customer,
                $chargeAmount,
                "Monthly subscription charge for {$package->name}",
                $subscription->uuid
            );

            if ($success) {
                // Create invoice record for auditing (prepaid invoices are for reference)
                $this->createReferenceInvoice($subscription, $periodStart, $periodEnd, $chargeAmount);
            }

            return $success;
        });
    }

    /**
     * Create a reference invoice for prepaid billing (for auditing purposes).
     */
    protected function createReferenceInvoice(Subscription $subscription, Carbon $periodStart, Carbon $periodEnd, int $amount): Invoice
    {
        $invoiceNumber = app(InvoiceNumberGenerator::class)->generateInvoiceNumber(
            $subscription->customer->tenant_id ?? 1
        );

        return Invoice::create([
            'tenant_id' => $subscription->customer->tenant_id,
            'uuid' => (string) \Str::orderedUuid(),
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'invoice_number' => $invoiceNumber,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'due_date' => $periodEnd->copy()->addDays(5)->toDateString(),
            'subtotal' => $amount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => $amount,
            'paid_amount' => $amount, // Prepaid - already paid via wallet
            'outstanding_amount' => 0,
            'status' => 'paid',
            'billing_type' => 'prepaid',
            'notes' => 'Prepaid billing - deducted from wallet balance',
            'created_by' => 1, // System user
            'updated_by' => 1,
        ]);
    }

    /**
     * Suspend subscription due to insufficient balance.
     */
    protected function suspendForInsufficientBalance(Subscription $subscription): void
    {
        $customer = $subscription->customer;

        // Change subscription status to suspended
        $subscription->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => 'Insufficient prepaid balance',
        ]);

        // Fire suspension event
        event(new \App\Events\Billing\SubscriptionSuspended(
            $customer,
            $this->getLastInvoice($subscription),
            'Insufficient prepaid balance'
        ));

        Log::warning("Prepaid subscription suspended", [
            'subscription_id' => $subscription->id,
            'customer_id' => $customer->id,
            'reason' => 'Insufficient balance',
            'current_balance' => $customer->wallet_balance,
        ]);
    }

    /**
     * Get the last invoice for a subscription (for suspension event).
     */
    protected function getLastInvoice(Subscription $subscription): ?Invoice
    {
        return $subscription->invoices()
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Reactivate prepaid subscription after wallet top-up.
     */
    public function reactivateSubscription(Subscription $subscription): bool
    {
        return DB::transaction(function () use ($subscription) {
            $customer = $subscription->customer;
            $package = $subscription->package;

            if (!$customer || !$package) {
                return false;
            }

            // Check if customer now has sufficient balance
            if (!$this->canActivateService($customer, $subscription)) {
                return false;
            }

            // Reactivate subscription
            $subscription->update([
                'status' => 'active',
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);

            // Fire reactivation event
            event(new \App\Events\Billing\SubscriptionReactivated(
                $customer,
                null, // No payment record for prepaid reactivation
                'Prepaid balance replenished'
            ));

            Log::info("Prepaid subscription reactivated", [
                'subscription_id' => $subscription->id,
                'customer_id' => $customer->id,
                'current_balance' => $customer->wallet_balance,
            ]);

            return true;
        });
    }

    /**
     * Get wallet balance in BDT (for display).
     */
    public function getBalanceInBdt(Customer $customer): string
    {
        return number_format($customer->wallet_balance / 100, 2);
    }

    /**
     * Get remaining balance in poysha.
     */
    public function getRemainingBalance(Customer $customer): int
    {
        return $customer->wallet_balance;
    }

    /**
     * Top up wallet via payment.
     */
    public function topUpWallet(Payment $payment): bool
    {
        return DB::transaction(function () use ($payment) {
            $customer = $payment->customer;

            if (!$customer) {
                return false;
            }

            $amount = $payment->amount;

            // Credit the wallet
            $success = $this->creditToBalance(
                $customer,
                $amount,
                "Wallet top-up via {$payment->method}",
                $payment->gateway_reference
            );

            if ($success) {
                // Update payment to mark as wallet top-up
                $payment->update([
                    'is_wallet_topup' => true,
                    'applied_to_invoice' => false,
                ]);

                // Check if any suspended subscriptions can be reactivated
                $this->reactivateSuspendedSubscriptions($customer);
            }

            return $success;
        });
    }

    /**
     * Reactivate all suspended subscriptions for customer if balance is sufficient.
     */
    protected function reactivateSuspendedSubscriptions(Customer $customer): void
    {
        $suspendedSubscriptions = $customer->subscriptions()
            ->where('status', 'suspended')
            ->where('suspension_reason', 'Insufficient prepaid balance')
            ->with('package')
            ->get();

        foreach ($suspendedSubscriptions as $subscription) {
            $this->reactivateSubscription($subscription);
        }
    }
}
