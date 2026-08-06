<?php

namespace App\Services\Reseller;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerCommissionLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * CommissionService
 *
 * Handles all reseller wallet movements. Every credit/debit is:
 *  1. Wrapped in a DB transaction (atomic).
 *  2. Written immutably to reseller_commission_ledger.
 *  3. Logged via Laravel's activity log (AGENTS.md rule 7).
 *
 * Money is always in poysha (BDT × 100) — never float.
 */
class CommissionService
{
    /**
     * Calculate and credit commission when a payment is received.
     * Called by CreditResellerCommissionOnPayment listener.
     */
    public function creditCommission(Payment $payment): void
    {
        // Resolve reseller: direct on payment, or via customer
        $reseller = $this->resolveReseller($payment);

        if ($reseller === null) {
            return; // No reseller associated — nothing to do
        }

        $commissionPoysha = $this->calculateCommission($reseller, $payment);

        if ($commissionPoysha <= 0) {
            return;
        }

        $this->creditWallet(
            reseller: $reseller,
            amountPoysha: $commissionPoysha,
            description: "Commission for Payment #{$payment->id} (Invoice #{$payment->invoice_id})",
            type: 'earned',
            paymentId: $payment->id,
            invoiceId: $payment->invoice_id,
        );
    }

    /**
     * Credit the reseller wallet (top-up, commission, adjustment).
     */
    public function creditWallet(
        Reseller $reseller,
        int $amountPoysha,
        string $description,
        string $type = 'earned',
        ?int $paymentId = null,
        ?int $invoiceId = null,
    ): ResellerCommissionLedger {
        if ($amountPoysha <= 0) {
            throw new RuntimeException("Credit amount must be positive. Got: {$amountPoysha}");
        }

        return DB::transaction(function () use ($reseller, $amountPoysha, $description, $type, $paymentId, $invoiceId) {
            // Lock the reseller row to prevent race conditions
            $reseller = Reseller::lockForUpdate()->findOrFail($reseller->id);

            $balanceBefore = $reseller->wallet_balance;
            $balanceAfter = $balanceBefore + $amountPoysha;

            $reseller->wallet_balance = $balanceAfter;

            if ($type === 'earned') {
                $reseller->total_earnings += $amountPoysha;
            }

            $reseller->save();

            $entry = ResellerCommissionLedger::create([
                'uuid' => Str::uuid(),
                'tenant_id' => $reseller->tenant_id,
                'reseller_id' => $reseller->id,
                'invoice_id' => $invoiceId,
                'payment_id' => $paymentId,
                'created_by' => auth()->id(),
                'type' => $type,
                'amount' => $amountPoysha,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
            ]);

            activity()
                ->performedOn($reseller)
                ->withProperties(['amount_poysha' => $amountPoysha, 'type' => $type, 'ledger_id' => $entry->id])
                ->log("Reseller wallet credited: {$description}");

            Log::info("Reseller wallet credited", [
                'reseller_id' => $reseller->id,
                'amount' => $amountPoysha,
                'type' => $type,
                'balance_after' => $balanceAfter,
            ]);

            return $entry;
        });
    }

    /**
     * Debit the reseller wallet (service charge, withdrawal).
     * Wallet balance must not go negative unless allowNegative is explicitly true.
     */
    public function debitWallet(
        Reseller $reseller,
        int $amountPoysha,
        string $description,
        string $type = 'withdrawn',
        bool $allowNegative = false,
    ): ResellerCommissionLedger {
        if ($amountPoysha <= 0) {
            throw new RuntimeException("Debit amount must be positive. Got: {$amountPoysha}");
        }

        return DB::transaction(function () use ($reseller, $amountPoysha, $description, $type, $allowNegative) {
            $reseller = Reseller::lockForUpdate()->findOrFail($reseller->id);

            $this->ensureSufficientBalance($reseller, $amountPoysha, $allowNegative);

            $balanceBefore = $reseller->wallet_balance;
            $balanceAfter = $balanceBefore - $amountPoysha;

            $reseller->wallet_balance = $balanceAfter;

            if ($type === 'withdrawn') {
                $reseller->total_withdrawn += $amountPoysha;
            }

            $reseller->save();

            $entry = ResellerCommissionLedger::create([
                'uuid' => Str::uuid(),
                'tenant_id' => $reseller->tenant_id,
                'reseller_id' => $reseller->id,
                'created_by' => auth()->id(),
                'type' => $type,
                'amount' => -$amountPoysha, // negative = debit
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
            ]);

            activity()
                ->performedOn($reseller)
                ->withProperties(['amount_poysha' => $amountPoysha, 'type' => $type, 'ledger_id' => $entry->id])
                ->log("Reseller wallet debited: {$description}");

            return $entry;
        });
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function resolveReseller(Payment $payment): ?Reseller
    {
        if ($payment->reseller_id) {
            return Reseller::find($payment->reseller_id);
        }

        // Fallback: look up through customer
        if ($payment->customer_id) {
            $customer = Customer::withoutGlobalScopes()->find($payment->customer_id);

            if ($customer?->reseller_id) {
                return Reseller::find($customer->reseller_id);
            }
        }

        return null;
    }

    private function calculateCommission(Reseller $reseller, Payment $payment): int
    {
        // Percentage commission takes priority over flat amount
        if ($reseller->commission_rate > 0) {
            // commission_rate is stored as whole percent (e.g. 10 = 10%)
            // payment->amount is in poysha
            return (int) round($payment->amount * $reseller->commission_rate / 100);
        }

        // Flat commission per payment
        if ($reseller->commission_amount > 0) {
            return $reseller->commission_amount;
        }

        return 0;
    }

    private function ensureSufficientBalance(Reseller $reseller, int $amount, bool $allowNegative): void
    {
        if ($allowNegative) {
            return; // Explicit override — log it but allow
        }

        if ($reseller->wallet_balance < $amount) {
            throw new RuntimeException(
                "Reseller #{$reseller->id} has insufficient wallet balance. " .
                "Balance: {$reseller->wallet_balance} poysha, Required: {$amount} poysha."
            );
        }
    }
}
