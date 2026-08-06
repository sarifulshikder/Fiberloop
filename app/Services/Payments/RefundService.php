<?php

namespace App\Services\Payments;

use App\Enums\CreditNoteStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for handling payment refunds with CreditNote integration.
 * Ensures proper auditing and reversal of payment allocations.
 */
class RefundService
{
    protected BkashService $bkashService;
    protected NagadService $nagadService;
    protected SSLCommerzService $sslCommerzService;
    protected IdempotencyService $idempotencyService;

    public function __construct(
        BkashService $bkashService,
        NagadService $nagadService,
        SSLCommerzService $sslCommerzService,
        IdempotencyService $idempotencyService
    ) {
        $this->bkashService = $bkashService;
        $this->nagadService = $nagadService;
        $this->sslCommerzService = $sslCommerzService;
        $this->idempotencyService = $idempotencyService;
    }

    /**
     * Get the appropriate gateway service for a payment method.
     */
    protected function getGatewayService(string $method)
    {
        switch ($method) {
            case PaymentMethod::BKASH->value:
                return $this->bkashService;
            case PaymentMethod::NAGAD->value:
                return $this->nagadService;
            case PaymentMethod::SSLCOMMERZ->value:
                return $this->sslCommerzService;
            default:
                return null;
        }
    }

    /**
     * Process a refund for a payment.
     * This handles both gateway refunds and manual refunds.
     *
     * @param Payment $payment The original payment to refund
     * @param int $amount Amount to refund in poysha
     * @param string $reason Reason for refund
     * @param int $processedBy User ID of the person processing the refund
     * @param string $idempotencyKey Optional idempotency key
     * @return array Result of the refund operation
     */
    public function processRefund(
        Payment $payment,
        int $amount,
        string $reason,
        int $processedBy,
        string $idempotencyKey = null
    ): array {
        DB::beginTransaction();

        try {
            $this->validateRefund($payment, $amount);

            $gateway = $payment->method;
            $gatewayService = $this->getGatewayService($gateway);

            // Generate a refund reference
            $refundReference = 'REFUND_' . $payment->uuid . '_' . Str::upper(Str::random(6));

            // Use idempotency if key provided
            if ($idempotencyKey) {
                return $this->idempotencyService->execute($idempotencyKey, function () use (
                    $payment,
                    $amount,
                    $reason,
                    $processedBy,
                    $gateway,
                    $gatewayService,
                    $refundReference
                ) {
                    return $this->executeRefund(
                        $payment,
                        $amount,
                        $reason,
                        $processedBy,
                        $gateway,
                        $gatewayService,
                        $refundReference
                    );
                });
            }

            return $this->executeRefund(
                $payment,
                $amount,
                $reason,
                $processedBy,
                $gateway,
                $gatewayService,
                $refundReference
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Refund processing failed', [
                'payment_id' => $payment->id,
                'amount' => $amount,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate refund request.
     */
    protected function validateRefund(Payment $payment, int $amount): void
    {
        if ($payment->status !== PaymentStatus::COMPLETED) {
            throw new \Exception('Cannot refund a payment that is not completed');
        }

        if ($amount <= 0) {
            throw new \Exception('Refund amount must be greater than 0');
        }

        if ($amount > $payment->amount) {
            throw new \Exception('Refund amount cannot exceed original payment amount');
        }

        // For non-wallet payments, we can only refund if there's an associated invoice
        if ($payment->invoice_id === null && !$payment->is_wallet_topup) {
            throw new \Exception('Cannot refund payment without associated invoice or wallet top-up');
        }
    }

    /**
     * Execute the refund operation.
     */
    protected function executeRefund(
        Payment $payment,
        int $amount,
        string $reason,
        int $processedBy,
        string $gateway,
        $gatewayService,
        string $refundReference
    ): array {
        $customer = $payment->customer;
        $invoice = $payment->invoice;
        $isWalletTopup = $payment->is_wallet_topup;

        $result = [
            'success' => false,
            'message' => '',
            'refund_id' => $refundReference,
            'gateway_refund_id' => null,
            'credit_note_id' => null,
            'amount' => $amount,
        ];

        // Step 1: Process gateway refund if applicable
        $gatewayRefundResult = null;

        if ($gatewayService && !$isWalletTopup) {
            try {
                $gatewayRefundResult = $gatewayService->refund($payment->gateway_reference, $amount);
                $result['gateway_refund_id'] = $gatewayRefundResult['refund_id'] ?? null;
            } catch (\Exception $e) {
                Log::error('Gateway refund failed, continuing with manual refund process', [
                    'payment_id' => $payment->id,
                    'gateway' => $gateway,
                    'error' => $e->getMessage(),
                ]);
                // Continue with manual refund process even if gateway refund fails
            }
        }

        // Step 2: Create CreditNote for the refund
        $creditNote = $this->createCreditNote($payment, $amount, $reason, $processedBy);
        $result['credit_note_id'] = $creditNote->id;

        // Step 3: Update payment and create refund records
        if ($isWalletTopup) {
            // For wallet top-ups, deduct from wallet
            $this->processWalletRefund($customer, $amount, $refundReference, $processedBy, $reason);
        } elseif ($invoice) {
            // For invoice payments, update invoice and create credit note application
            $this->processInvoiceRefund($invoice, $payment, $amount, $refundReference, $processedBy);
        }

        // Step 4: Create refund payment record
        $refundPayment = $this->createRefundPayment(
            $payment,
            $amount,
            $refundReference,
            $processedBy,
            $creditNote,
            $gatewayRefundResult
        );

        // Step 5: Update original payment
        $this->updateOriginalPayment($payment, $amount, $refundReference, $processedBy);

        DB::commit();

        $result['success'] = true;
        $result['message'] = 'Refund processed successfully';

        Log::info('Refund processed successfully', [
            'payment_id' => $payment->id,
            'amount' => $amount,
            'refund_id' => $refundReference,
            'credit_note_id' => $creditNote->id,
            'gateway_refund_id' => $result['gateway_refund_id'],
            'processed_by' => $processedBy,
        ]);

        return $result;
    }

    /**
     * Create a CreditNote for the refund.
     */
    protected function createCreditNote(Payment $payment, int $amount, string $reason, int $createdBy): CreditNote
    {
        $invoice = $payment->invoice;
        $customer = $payment->customer;

        $creditNote = CreditNote::create([
            'tenant_id' => $customer->tenant_id,
            'uuid' => (string) Str::orderedUuid(),
            'customer_id' => $customer->id,
            'invoice_id' => $invoice?->id,
            'created_by' => $createdBy,
            'approved_by' => $createdBy, // Auto-approved for system-initiated refunds
            'credit_note_number' => $this->generateCreditNoteNumber($customer->tenant_id),
            'reason' => $reason,
            'issue_date' => now()->toDateString(),
            'subtotal' => $amount,
            'tax_amount' => 0, // Tax handled separately if needed
            'total' => $amount,
            'status' => CreditNoteStatus::APPROVED, // Auto-approved
            'approved_at' => now(),
            'applied_at' => now(),
            'notes' => 'Generated from refund of payment ' . $payment->uuid,
        ]);

        return $creditNote;
    }

    /**
     * Generate a credit note number.
     */
    protected function generateCreditNoteNumber(int $tenantId): string
    {
        $datePart = now()->format('Ymd');
        $tenantPrefix = 'CN' . str_pad($tenantId, 4, '0', STR_PAD_LEFT);

        $lastCreditNote = CreditNote::where('tenant_id', $tenantId)
            ->where('credit_note_number', 'LIKE', "{$tenantPrefix}{$datePart}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastCreditNote) {
            $lastNumber = substr($lastCreditNote->credit_note_number, -4);
            $newNumber = str_pad((int)$lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $tenantPrefix . $datePart . $newNumber;
    }

    /**
     * Process wallet refund (deduct from wallet for prepaid customers).
     */
    protected function processWalletRefund($customer, int $amount, string $refundReference, int $processedBy, string $reason): void
    {
        $currentBalance = $customer->wallet_balance;
        $newBalance = $currentBalance - $amount;

        if ($newBalance < 0) {
            throw new \Exception('Insufficient wallet balance for refund');
        }

        // Update customer wallet balance
        $customer->update([
            'wallet_balance' => $newBalance,
            'updated_by' => $processedBy,
        ]);

        // Record wallet transaction for audit
        WalletTransaction::recordDebit(
            $customer,
            $amount,
            'Refund: ' . $reason . ' (Ref: ' . $refundReference . ')',
            'refund',
            null,
            $processedBy,
            ['refund_reference' => $refundReference]
        );

        Log::info('Wallet refund processed', [
            'customer_id' => $customer->id,
            'amount' => $amount,
            'old_balance' => $currentBalance,
            'new_balance' => $newBalance,
            'refund_reference' => $refundReference,
        ]);
    }

    /**
     * Process invoice refund (update invoice amounts).
     */
    protected function processInvoiceRefund(
        Invoice $invoice,
        Payment $originalPayment,
        int $amount,
        string $refundReference,
        int $processedBy
    ): void {
        // Calculate new amounts
        $newPaidAmount = $invoice->paid_amount - $amount;
        $newOutstandingAmount = $invoice->outstanding_amount + $amount;

        // Update invoice
        $invoice->update([
            'paid_amount' => $newPaidAmount,
            'outstanding_amount' => $newOutstandingAmount,
            'status' => $newOutstandingAmount > 0 ? ($newPaidAmount > 0 ? 'partial' : 'unpaid') : 'paid',
            'updated_by' => $processedBy,
        ]);

        Log::info('Invoice refund processed', [
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'new_paid_amount' => $newPaidAmount,
            'new_outstanding_amount' => $newOutstandingAmount,
            'refund_reference' => $refundReference,
        ]);
    }

    /**
     * Create refund payment record.
     */
    protected function createRefundPayment(
        Payment $originalPayment,
        int $amount,
        string $refundReference,
        int $processedBy,
        CreditNote $creditNote,
        ?array $gatewayRefundResult
    ): Payment {
        $refundPayment = Payment::create([
            'tenant_id' => $originalPayment->tenant_id,
            'uuid' => (string) Str::orderedUuid(),
            'invoice_id' => $originalPayment->invoice_id,
            'customer_id' => $originalPayment->customer_id,
            'reseller_id' => $originalPayment->reseller_id,
            'amount' => $amount,
            'fee_amount' => 0,
            'net_amount' => -$amount, // Negative for refund
            'method' => $originalPayment->method,
            'status' => PaymentStatus::REFUNDED,
            'gateway_reference' => $refundReference,
            'gateway_response' => $gatewayRefundResult ? json_encode($gatewayRefundResult) : null,
            'paid_at' => now(),
            'notes' => 'Refund of payment ' . $originalPayment->uuid . ' - ' . ($gatewayRefundResult ? 'Gateway refund: ' . ($gatewayRefundResult['refund_id'] ?? 'N/A') : 'Manual refund'),
            'receipt_path' => null,
            'collected_by' => null,
            'created_by' => $processedBy,
            'updated_by' => $processedBy,
            'split_from_payment_id' => null,
            'is_partial' => false,
            'is_wallet_topup' => false,
            'applied_to_invoice' => false,
        ]);

        return $refundPayment;
    }

    /**
     * Update original payment after refund.
     */
    protected function updateOriginalPayment(
        Payment $payment,
        int $amount,
        string $refundReference,
        int $processedBy
    ): void {
        // If full refund, mark as refunded
        if ($amount === $payment->amount) {
            $payment->update([
                'status' => PaymentStatus::REFUNDED,
                'failure_reason' => 'Fully refunded: ' . $refundReference,
                'updated_by' => $processedBy,
            ]);
        } else {
            // Partial refund - update notes but keep as completed
            $payment->update([
                'notes' => $payment->notes . (empty($payment->notes) ? '' : ' | ') .
                         'Partial refund: ' . $amount . ' poysha (Ref: ' . $refundReference . ')',
                'updated_by' => $processedBy,
            ]);
        }
    }

    /**
     * Process a refund without a payment record (manual refund).
     * This is useful for creating credit notes without an original payment.
     */
    public function processManualRefund(
        int $customerId,
        int $amount,
        string $reason,
        int $processedBy,
        int $invoiceId = null
    ): array {
        DB::beginTransaction();

        try {
            $customer = \App\Models\Customer::findOrFail($customerId);
            $invoice = $invoiceId ? Invoice::findOrFail($invoiceId) : null;

            if ($invoice && $invoice->customer_id !== $customer->id) {
                throw new \Exception('Invoice does not belong to the specified customer');
            }

            // Create CreditNote
            $creditNote = CreditNote::create([
                'tenant_id' => $customer->tenant_id,
                'uuid' => (string) Str::orderedUuid(),
                'customer_id' => $customer->id,
                'invoice_id' => $invoice?->id,
                'created_by' => $processedBy,
                'approved_by' => $processedBy,
                'credit_note_number' => $this->generateCreditNoteNumber($customer->tenant_id),
                'reason' => $reason,
                'issue_date' => now()->toDateString(),
                'subtotal' => $amount,
                'tax_amount' => 0,
                'total' => $amount,
                'status' => CreditNoteStatus::APPROVED,
                'approved_at' => now(),
                'applied_at' => now(),
                'notes' => 'Manual refund',
            ]);

            // If there's an invoice, apply the credit note to it
            if ($invoice) {
                // Update invoice
                $newPaidAmount = max(0, $invoice->paid_amount - $amount);
                $newOutstandingAmount = $invoice->outstanding_amount + $amount;

                $invoice->update([
                    'paid_amount' => $newPaidAmount,
                    'outstanding_amount' => $newOutstandingAmount,
                    'status' => $newOutstandingAmount > 0 ? ($newPaidAmount > 0 ? 'partial' : 'unpaid') : 'paid',
                    'updated_by' => $processedBy,
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Manual refund processed successfully',
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Manual refund failed', [
                'customer_id' => $customerId,
                'amount' => $amount,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get refund history for a customer.
     */
    public function getCustomerRefundHistory(int $customerId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return Payment::where('customer_id', $customerId)
            ->whereIn('status', [PaymentStatus::REFUNDED])
            ->orWhere(function ($query) use ($customerId) {
                $query->where('customer_id', $customerId)
                    ->where('net_amount', '<', 0); // Refund payments have negative net_amount
            })
            ->with(['invoice', 'createdBy', 'creditNotes'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if a payment can be refunded.
     */
    public function canRefund(Payment $payment): bool
    {
        if ($payment->status !== PaymentStatus::COMPLETED) {
            return false;
        }

        // Payment must have an associated invoice or be a wallet top-up
        if ($payment->invoice_id === null && !$payment->is_wallet_topup) {
            return false;
        }

        return true;
    }
}
