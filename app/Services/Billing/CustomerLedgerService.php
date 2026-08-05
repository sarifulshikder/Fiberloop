<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for generating customer ledger/statement views.
 * Provides a reconciled view per customer with running balance,
 * all invoices, payments, and credits.
 */
class CustomerLedgerService
{
    /**
     * Get the running balance for a customer.
     * Positive = customer owes money (credit to ISP)
     * Negative = ISP owes money (credit to customer)
     * All amounts in poysha.
     */
    public function getRunningBalance(Customer $customer, Carbon $atDate = null): int
    {
        $atDate = $atDate ?? now();
        
        // Sum of all invoice totals (amounts customer owes)
        $totalInvoiced = $this->getTotalInvoiced($customer, $atDate);
        
        // Sum of all payments received (amounts customer paid)
        $totalPaid = $this->getTotalPaid($customer, $atDate);
        
        // Sum of all credit notes (amounts credited to customer)
        $totalCredited = $this->getTotalCredited($customer, $atDate);
        
        // Sum of all refunds (amounts refunded to customer)
        $totalRefunded = $this->getTotalRefunded($customer, $atDate);
        
        // Running balance = invoiced - paid - credited - refunded
        return $totalInvoiced - $totalPaid - $totalCredited - $totalRefunded;
    }

    /**
     * Get formatted running balance for display (BDT).
     */
    public function getFormattedRunningBalance(Customer $customer, Carbon $atDate = null): string
    {
        $balance = $this->getRunningBalance($customer, $atDate);
        $bdt = $balance / 100;
        
        $sign = $balance >= 0 ? '' : '-';
        $absBalance = abs($balance);
        
        return $sign . 'BDT ' . number_format($absBalance / 100, 2);
    }

    /**
     * Get total amount invoiced for customer up to date.
     */
    protected function getTotalInvoiced(Customer $customer, Carbon $atDate): int
    {
        return Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['draft', 'sent', 'paid', 'partial', 'overdue'])
            ->where('created_at', '<=', $atDate->toDateTimeString())
            ->sum('total');
    }

    /**
     * Get total amount paid by customer up to date.
     */
    protected function getTotalPaid(Customer $customer, Carbon $atDate): int
    {
        return Payment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->where('paid_at', '<=', $atDate->toDateTimeString())
            ->sum('amount');
    }

    /**
     * Get total amount credited to customer via credit notes up to date.
     */
    protected function getTotalCredited(Customer $customer, Carbon $atDate): int
    {
        return CreditNote::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['issued', 'applied'])
            ->where('created_at', '<=', $atDate->toDateTimeString())
            ->sum('amount');
    }

    /**
     * Get total amount refunded to customer up to date.
     */
    protected function getTotalRefunded(Customer $customer, Carbon $atDate): int
    {
        return Refund::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['approved', 'processed'])
            ->where('created_at', '<=', $atDate->toDateTimeString())
            ->sum('amount');
    }

    /**
     * Get the full ledger/statement for a customer.
     * Returns a collection of transactions with running balance.
     */
    public function getStatement(Customer $customer, Carbon $fromDate = null, Carbon $toDate = null): Collection
    {
        $fromDate = $fromDate ?? now()->subYear();
        $toDate = $toDate ?? now();
        
        // Get all transactions in order
        $transactions = collect();
        
        // Add invoices
        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['draft', 'sent', 'paid', 'partial', 'overdue'])
            ->whereBetween('created_at', [$fromDate->toDateTimeString(), $toDate->toDateTimeString()])
            ->orderBy('created_at')
            ->get()
            ->map(function ($invoice) {
                return [
                    'date' => $invoice->created_at,
                    'type' => 'invoice',
                    'reference' => $invoice->invoice_number,
                    'description' => 'Invoice for ' . ($invoice->subscription->package->name ?? 'Service'),
                    'amount' => $invoice->total,
                    'balance' => null, // Will be calculated later
                    'status' => $invoice->status->value,
                    'invoice' => $invoice,
                ];
            });
        
        // Add payments
        $payments = Payment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$fromDate->toDateTimeString(), $toDate->toDateTimeString()])
            ->orderBy('paid_at')
            ->get()
            ->map(function ($payment) {
                return [
                    'date' => $payment->paid_at,
                    'type' => 'payment',
                    'reference' => $payment->gateway_reference ?? $payment->id,
                    'description' => 'Payment via ' . $payment->method->value,
                    'amount' => -$payment->amount, // Negative because it reduces balance
                    'balance' => null,
                    'status' => $payment->status->value,
                    'payment' => $payment,
                ];
            });
        
        // Add credit notes
        $creditNotes = CreditNote::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['issued', 'applied'])
            ->whereBetween('created_at', [$fromDate->toDateTimeString(), $toDate->toDateTimeString()])
            ->orderBy('created_at')
            ->get()
            ->map(function ($creditNote) {
                return [
                    'date' => $creditNote->created_at,
                    'type' => 'credit_note',
                    'reference' => $creditNote->credit_note_number,
                    'description' => 'Credit note: ' . $creditNote->reason,
                    'amount' => -$creditNote->amount, // Negative because it reduces balance
                    'balance' => null,
                    'status' => $creditNote->status->value,
                    'credit_note' => $creditNote,
                ];
            });
        
        // Add refunds
        $refunds = Refund::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['approved', 'processed'])
            ->whereBetween('created_at', [$fromDate->toDateTimeString(), $toDate->toDateTimeString()])
            ->orderBy('created_at')
            ->get()
            ->map(function ($refund) {
                return [
                    'date' => $refund->created_at,
                    'type' => 'refund',
                    'reference' => $refund->refund_number,
                    'description' => 'Refund: ' . $refund->reason,
                    'amount' => -$refund->amount, // Negative because it reduces balance
                    'balance' => null,
                    'status' => $refund->status->value,
                    'refund' => $refund,
                ];
            });
        
        // Merge all transactions and sort by date
        $transactions = $transactions->merge($invoices)->merge($payments)->merge($creditNotes)->merge($refunds)
            ->sortBy('date');
        
        // Calculate running balance
        $runningBalance = 0;
        $balancedTransactions = collect();
        
        foreach ($transactions as $transaction) {
            $runningBalance += $transaction['amount'];
            $transaction['balance'] = $runningBalance;
            $balancedTransactions->push($transaction);
        }
        
        return $balancedTransactions;
    }

    /**
     * Get statement summary for a customer.
     */
    public function getStatementSummary(Customer $customer, Carbon $fromDate = null, Carbon $toDate = null): array
    {
        $statement = $this->getStatement($customer, $fromDate, $toDate);
        
        $totalInvoiced = 0;
        $totalPaid = 0;
        $totalCredited = 0;
        $totalRefunded = 0;
        
        foreach ($statement as $transaction) {
            switch ($transaction['type']) {
                case 'invoice':
                    $totalInvoiced += $transaction['amount'];
                    break;
                case 'payment':
                    $totalPaid += abs($transaction['amount']);
                    break;
                case 'credit_note':
                    $totalCredited += abs($transaction['amount']);
                    break;
                case 'refund':
                    $totalRefunded += abs($transaction['amount']);
                    break;
            }
        }
        
        $endingBalance = end($statement)['balance'] ?? 0;
        
        return [
            'period_start' => $fromDate ?? now()->subYear(),
            'period_end' => $toDate ?? now(),
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'total_credited' => $totalCredited,
            'total_refunded' => $totalRefunded,
            'ending_balance' => $endingBalance,
            'ending_balance_formatted' => $this->formatAmount($endingBalance),
            'total_invoiced_formatted' => $this->formatAmount($totalInvoiced),
            'total_paid_formatted' => $this->formatAmount($totalPaid),
            'total_credited_formatted' => $this->formatAmount($totalCredited),
            'total_refunded_formatted' => $this->formatAmount($totalRefunded),
        ];
    }

    /**
     * Format amount from poysha to BDT for display.
     */
    public function formatAmount(int $poysha): string
    {
        $bdt = $poysha / 100;
        return number_format($bdt, 2);
    }

    /**
     * Get customer's current account status.
     */
    public function getAccountStatus(Customer $customer): string
    {
        $balance = $this->getRunningBalance($customer);
        
        if ($balance > 0) {
            return 'OWES_MONEY';
        } elseif ($balance < 0) {
            return 'CREDIT_BALANCE';
        } else {
            return 'BALANCED';
        }
    }

    /**
     * Get recent transactions for customer dashboard.
     */
    public function getRecentTransactions(Customer $customer, int $limit = 10): Collection
    {
        return $this->getStatement($customer)
            ->take(-$limit)
            ->values();
    }

    /**
     * Get overdue invoices for customer.
     */
    public function getOverdueInvoices(Customer $customer): Collection
    {
        return Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['sent', 'overdue', 'partial'])
            ->where('due_date', '<', now()->toDateString())
            ->where('outstanding_amount', '>', 0)
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Get upcoming invoices (not yet due) for customer.
     */
    public function getUpcomingInvoices(Customer $customer): Collection
    {
        return Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['draft', 'sent', 'partial'])
            ->where('due_date', '>=', now()->toDateString())
            ->where('outstanding_amount', '>', 0)
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Check if customer has any overdue invoices.
     */
    public function hasOverdueInvoices(Customer $customer): bool
    {
        return $this->getOverdueInvoices($customer)->count() > 0;
    }
}