<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling late fee calculation and application.
 * Grace period: 5 days (configurable)
 * Late fee: Configurable percentage or fixed amount
 */
class LateFeeService
{
    protected int $gracePeriodDays = 5;
    protected int $lateFeePercentage = 10; // 10% late fee
    protected int $lateFeeFixed = 0; // Fixed late fee in poysha (0 = use percentage)
    protected int $maxLateFee = 0; // Maximum late fee in poysha (0 = no limit)

    /**
     * Set grace period in days.
     */
    public function setGracePeriod(int $days): self
    {
        $this->gracePeriodDays = $days;
        return $this;
    }

    /**
     * Set late fee as percentage.
     */
    public function setLateFeePercentage(int $percentage): self
    {
        $this->lateFeePercentage = $percentage;
        $this->lateFeeFixed = 0;
        return $this;
    }

    /**
     * Set fixed late fee amount in poysha.
     */
    public function setLateFeeFixed(int $poysha): self
    {
        $this->lateFeeFixed = $poysha;
        $this->lateFeePercentage = 0;
        return $this;
    }

    /**
     * Set maximum late fee in poysha.
     */
    public function setMaxLateFee(int $poysha): self
    {
        $this->maxLateFee = $poysha;
        return $this;
    }

    /**
     * Check if an invoice is eligible for late fee.
     */
    public function isEligibleForLateFee(Invoice $invoice): bool
    {
        // Only apply late fees to unpaid, non-void invoices that are past grace period
        if ($invoice->status->isPaid() || $invoice->status->isVoid()) {
            return false;
        }

        if ($invoice->outstanding_amount <= 0) {
            return false;
        }

        $dueDate = Carbon::parse($invoice->due_date);
        $gracePeriodEnd = $dueDate->copy()->addDays($this->gracePeriodDays);

        return now()->isAfter($gracePeriodEnd);
    }

    /**
     * Calculate late fee amount for an invoice.
     * Returns amount in poysha.
     */
    public function calculateLateFee(Invoice $invoice): int
    {
        if (!$this->isEligibleForLateFee($invoice)) {
            return 0;
        }

        $outstanding = $invoice->outstanding_amount;

        if ($this->lateFeeFixed > 0) {
            // Fixed late fee
            $lateFee = $this->lateFeeFixed;
        } else {
            // Percentage late fee
            $lateFee = (int) round($outstanding * $this->lateFeePercentage / 100);
        }

        // Apply maximum limit if configured
        if ($this->maxLateFee > 0 && $lateFee > $this->maxLateFee) {
            $lateFee = $this->maxLateFee;
        }

        return $lateFee;
    }

    /**
     * Apply late fee to an invoice.
     * Creates a new invoice item for the late fee.
     */
    public function applyLateFee(Invoice $invoice): int
    {
        return DB::transaction(function () use ($invoice) {
            $lateFee = $this->calculateLateFee($invoice);

            if ($lateFee <= 0) {
                return 0;
            }

            // Check if late fee already applied for this period
            $existingLateFee = $invoice->items()
                ->where('item_type', 'late_fee')
                ->where('period_start', $invoice->due_date->copy()->addDay()->toDateString())
                ->first();

            if ($existingLateFee) {
                Log::info("Late fee already applied to invoice {$invoice->id}");
                return 0;
            }

            // Add late fee as a new invoice item
            $invoice->items()->create([
                'tenant_id' => $invoice->tenant_id,
                'description' => "Late Fee - {$this->lateFeePercentage}% for overdue payment",
                'item_type' => 'late_fee',
                'quantity' => 1,
                'unit_price' => $lateFee,
                'amount' => $lateFee,
                'period_start' => $invoice->due_date->copy()->addDay()->toDateString(),
                'period_end' => now()->toDateString(),
                'metadata' => [
                    'late_fee_percentage' => $this->lateFeePercentage,
                    'grace_period_days' => $this->gracePeriodDays,
                    'applied_at' => now()->toIsoString(),
                ],
            ]);

            // Update invoice totals
            $invoice->update([
                'total' => $invoice->total + $lateFee,
                'outstanding_amount' => $invoice->outstanding_amount + $lateFee,
            ]);

            Log::info("Late fee applied", [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'late_fee_amount' => $lateFee,
                'new_outstanding' => $invoice->outstanding_amount,
            ]);

            return $lateFee;
        });
    }

    /**
     * Process late fees for all overdue invoices.
     */
    public function processAllOverdueInvoices(): array
    {
        $processed = [];
        $totalLateFees = 0;

        $overdueInvoices = Invoice::query()
            ->whereIn('status', ['sent', 'overdue', 'partial'])
            ->where('due_date', '<', now()->subDays($this->gracePeriodDays)->toDateString())
            ->where('outstanding_amount', '>', 0)
            ->with(['customer', 'items'])
            ->get();

        foreach ($overdueInvoices as $invoice) {
            $lateFee = $this->applyLateFee($invoice);
            if ($lateFee > 0) {
                $processed[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_id' => $invoice->customer_id,
                    'late_fee' => $lateFee,
                ];
                $totalLateFees += $lateFee;
            }
        }

        return [
            'total_invoices_processed' => count($processed),
            'total_late_fees_applied' => $totalLateFees,
            'processed_invoices' => $processed,
        ];
    }

    /**
     * Get the grace period end date for an invoice.
     */
    public function getGracePeriodEnd(Invoice $invoice): Carbon
    {
        return Carbon::parse($invoice->due_date)->addDays($this->gracePeriodDays);
    }

    /**
     * Check if invoice is within grace period.
     */
    public function isWithinGracePeriod(Invoice $invoice): bool
    {
        if ($invoice->status->isPaid()) {
            return false;
        }

        $gracePeriodEnd = $this->getGracePeriodEnd($invoice);
        return now()->isBefore($gracePeriodEnd) || now()->isSameDay($gracePeriodEnd);
    }

    /**
     * Check if invoice has passed grace period.
     */
    public function hasPassedGracePeriod(Invoice $invoice): bool
    {
        if ($invoice->status->isPaid()) {
            return false;
        }

        $gracePeriodEnd = $this->getGracePeriodEnd($invoice);
        return now()->isAfter($gracePeriodEnd);
    }
}
