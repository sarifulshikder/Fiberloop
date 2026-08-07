<?php

namespace App\Jobs\Reconciliation;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Financial Reconciliation Health Check Job
 *
 * This job performs data integrity checks to ensure financial data consistency:
 * - Invoice payments sum should reconcile with ledger balances
 * - Wallet transactions should balance correctly
 * - No orphaned records (payments without invoices, etc.)
 *
 * Runs as a scheduled job in production for health monitoring.
 */
class FinancialReconciliationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $retryAfter = 0;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::channel('reconciliation')->info('Starting financial reconciliation check');

        $discrepancies = [];

        // Check 1: Invoice payment reconciliation
        $discrepancies = array_merge($discrepancies, $this->checkInvoicePaymentReconciliation());

        // Check 2: Wallet balance reconciliation
        $discrepancies = array_merge($discrepancies, $this->checkWalletReconciliation());

        // Check 3: Orphaned payments (payments without matching invoices)
        $discrepancies = array_merge($discrepancies, $this->checkOrphanedPayments());

        // Check 4: Negative outstanding amounts
        $discrepancies = array_merge($discrepancies, $this->checkNegativeOutstandingInvoices());

        // Check 5: Duplicate invoice numbers
        $discrepancies = array_merge($discrepancies, $this->checkDuplicateInvoiceNumbers());

        if (empty($discrepancies)) {
            Log::channel('reconciliation')->info('Financial reconciliation check passed - no discrepancies found');
            return;
        }

        // Log and alert about discrepancies
        $this->logAndAlertDiscrepancies($discrepancies);
    }

    /**
     * Check that invoice payments reconcile with outstanding amounts.
     */
    protected function checkInvoicePaymentReconciliation(): array
    {
        $discrepancies = [];

        // Get all paid invoices with their payments
        $invoices = Invoice::query()
            ->whereIn('status', ['paid', 'partial'])
            ->withSum('payments', 'amount')
            ->get();

        foreach ($invoices as $invoice) {
            $expectedPaid = $invoice->total - $invoice->outstanding_amount;
            $actualPaid = $invoice->payments_sum_amount ?? 0;

            if ($expectedPaid !== $actualPaid) {
                $discrepancies[] = [
                    'type' => 'invoice_payment_mismatch',
                    'severity' => 'critical',
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'expected_paid' => $expectedPaid,
                    'actual_paid' => $actualPaid,
                    'difference' => $expectedPaid - $actualPaid,
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * Check wallet transaction balance.
     */
    protected function checkWalletReconciliation(): array
    {
        $discrepancies = [];

        // This is a simplified check - in a real implementation, you'd need to
        // calculate the expected balance from all transactions
        // For now, we just check that wallet transactions have valid data

        $invalidTransactions = WalletTransaction::query()
            ->whereNull('wallet_id')
            ->orWhere('amount', 0)
            ->get();

        foreach ($invalidTransactions as $transaction) {
            $discrepancies[] = [
                'type' => 'invalid_wallet_transaction',
                'severity' => 'warning',
                'transaction_id' => $transaction->id,
                'issue' => empty($transaction->wallet_id) ? 'missing_wallet_id' : 'zero_amount',
            ];
        }

        return $discrepancies;
    }

    /**
     * Check for payments without matching invoices.
     */
    protected function checkOrphanedPayments(): array
    {
        $discrepancies = [];

        $payments = Payment::query()
            ->whereNull('invoice_id')
            ->whereNull('subscription_id')
            ->doesntHave('invoice')
            ->get();

        foreach ($payments as $payment) {
            $discrepancies[] = [
                'type' => 'orphaned_payment',
                'severity' => 'warning',
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'customer_id' => $payment->customer_id,
            ];
        }

        return $discrepancies;
    }

    /**
     * Check for invoices with negative outstanding amounts.
     */
    protected function checkNegativeOutstandingInvoices(): array
    {
        $discrepancies = [];

        $invoices = Invoice::query()
            ->where('outstanding_amount', '<', 0)
            ->get();

        foreach ($invoices as $invoice) {
            $discrepancies[] = [
                'type' => 'negative_outstanding_invoice',
                'severity' => 'critical',
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'outstanding_amount' => $invoice->outstanding_amount,
            ];
        }

        return $discrepancies;
    }

    /**
     * Check for duplicate invoice numbers.
     */
    protected function checkDuplicateInvoiceNumbers(): array
    {
        $discrepancies = [];

        $duplicates = Invoice::query()
            ->selectRaw('invoice_number, COUNT(*) as count')
            ->groupBy('invoice_number')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $discrepancies[] = [
                'type' => 'duplicate_invoice_number',
                'severity' => 'critical',
                'invoice_number' => $duplicate->invoice_number,
                'count' => $duplicate->count,
            ];
        }

        return $discrepancies;
    }

    /**
     * Log discrepancies and send alerts.
     */
    protected function logAndAlertDiscrepancies(array $discrepancies): void
    {
        $criticalCount = count(array_filter($discrepancies, fn ($d) => $d['severity'] === 'critical'));
        $warningCount = count(array_filter($discrepancies, fn ($d) => $d['severity'] === 'warning'));

        Log::channel('reconciliation')->error(
            "Financial reconciliation found {$criticalCount} critical and {$warningCount} warning discrepancies"
        );

        // Log each discrepancy in detail
        foreach ($discrepancies as $discrepancy) {
            $context = [
                'discrepancy_type' => $discrepancy['type'],
                'severity' => $discrepancy['severity'],
            ];

            if (isset($discrepancy['invoice_id'])) {
                $context['invoice_id'] = $discrepancy['invoice_id'];
            }
            if (isset($discrepancy['invoice_number'])) {
                $context['invoice_number'] = $discrepancy['invoice_number'];
            }
            if (isset($discrepancy['payment_id'])) {
                $context['payment_id'] = $discrepancy['payment_id'];
            }
            if (isset($discrepancy['amount'])) {
                $context['amount'] = $discrepancy['amount'];
            }
            if (isset($discrepancy['difference'])) {
                $context['difference'] = $discrepancy['difference'];
            }

            if ($discrepancy['severity'] === 'critical') {
                Log::channel('reconciliation')->error('Reconciliation discrepancy detected', $context);
            } else {
                Log::channel('reconciliation')->warning('Reconciliation discrepancy detected', $context);
            }
        }

        // In production, you would also send email/SMS alerts
        // For now, we'll just log to the reconciliation channel
        if (app()->environment('production')) {
            // Send email alert (implementation would be added here)
        }
    }
}
