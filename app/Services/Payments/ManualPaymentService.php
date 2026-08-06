<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\Billing\PaymentReceived;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\PrepaidService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for handling manual/cash payments collected by field agents.
 * Ensures proper attribution and auditing for cash payments.
 */
class ManualPaymentService
{
    protected PrepaidService $prepaidService;

    public function __construct(PrepaidService $prepaidService)
    {
        $this->prepaidService = $prepaidService;
    }

    /**
     * Record a manual/cash payment.
     *
     * @param array $data Payment data including:
     *              - customer_id: ID of the customer
     *              - invoice_id: ID of the invoice being paid (optional, can be null for wallet top-up)
     *              - amount: Amount paid in poysha
     *              - collected_by: User ID of the field agent collecting payment
     *              - collection_date: Date when payment was collected
     *              - location: Collection location/address
     *              - receipt_number: Manual receipt number
     *              - receipt_path: Path to uploaded receipt image
     *              - notes: Additional notes
     *              - is_wallet_topup: Whether this payment is for wallet top-up
     * @return Payment The created payment record
     */
    public function recordPayment(array $data): Payment
    {
        DB::beginTransaction();

        try {
            // Validate required fields
            $this->validateData($data);

            $customerId = $data['customer_id'];
            $invoiceId = $data['invoice_id'] ?? null;
            $amount = $data['amount'];
            $collectedBy = $data['collected_by'];
            $collectionDate = $data['collection_date'] ?? now();
            $isWalletTopup = $data['is_wallet_topup'] ?? false;

            // Get customer and invoice
            $customer = \App\Models\Customer::findOrFail($customerId);
            $invoice = $invoiceId ? Invoice::find($invoiceId) : null;

            // If this is for a specific invoice, validate it belongs to the customer
            if ($invoice && $invoice->customer_id !== $customer->id) {
                throw new \Exception('Invoice does not belong to the specified customer');
            }

            // Determine if this is a prepaid customer
            $subscription = $customer->subscriptions()->active()->first();
            $isPrepaid = $subscription ? $subscription->billing_type === 'prepaid' : false;

            // For prepaid customers, if this is a wallet top-up
            if ($isPrepaid && $isWalletTopup) {
                $payment = $this->recordWalletTopupPayment($data, $customer, $collectedBy, $collectionDate);
            }
            // For postpaid customers or specific invoice payments
            elseif ($invoice) {
                $payment = $this->recordInvoicePayment($data, $customer, $invoice, $collectedBy, $collectionDate);
            }
            // For prepaid customers making general payments (top-up to wallet)
            elseif ($isPrepaid) {
                $payment = $this->recordWalletTopupPayment($data, $customer, $collectedBy, $collectionDate);
            } else {
                throw new \Exception('Either invoice_id or is_wallet_topup must be provided for manual payment');
            }

            DB::commit();
            return $payment;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to record manual payment', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Validate payment data.
     */
    protected function validateData(array $data): void
    {
        if (empty($data['customer_id'])) {
            throw new \Exception('customer_id is required');
        }

        if (empty($data['amount']) || $data['amount'] <= 0) {
            throw new \Exception('amount must be greater than 0');
        }

        if (empty($data['collected_by'])) {
            throw new \Exception('collected_by (field agent ID) is required');
        }

        // Validate that the field agent exists
        if (!\App\Models\User::find($data['collected_by'])) {
            throw new \Exception('Invalid field agent ID');
        }
    }

    /**
     * Record payment for a specific invoice.
     */
    protected function recordInvoicePayment(
        array $data,
        $customer,
        Invoice $invoice,
        int $collectedBy,
        $collectionDate
    ): Payment {
        $amount = $data['amount'];
        $notes = $data['notes'] ?? '';
        $receiptNumber = $data['receipt_number'] ?? '';
        $receiptPath = $data['receipt_path'] ?? null;
        $location = $data['location'] ?? '';

        // Create the payment record
        $payment = Payment::create([
            'tenant_id' => $customer->tenant_id,
            'uuid' => (string) Str::orderedUuid(),
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'reseller_id' => $customer->reseller_id,
            'amount' => $amount,
            'fee_amount' => 0,
            'net_amount' => $amount,
            'method' => PaymentMethod::CASH,
            'status' => PaymentStatus::COMPLETED,
            'gateway_reference' => $receiptNumber,
            'gateway_response' => null,
            'paid_at' => $collectionDate,
            'notes' => $notes . ($location ? " | Collected at: {$location}" : ''),
            'receipt_path' => $receiptPath,
            'collected_by' => $collectedBy,
            'created_by' => $collectedBy,
            'updated_by' => $collectedBy,
        ]);

        // Update the invoice
        $invoice->update([
            'paid_amount' => DB::raw('paid_amount + ' . $amount),
            'outstanding_amount' => DB::raw('outstanding_amount - ' . $amount),
            'status' => $invoice->outstanding_amount - $amount <= 0 ? 'paid' : 'partial',
            'paid_at' => $collectionDate,
            'updated_by' => $collectedBy,
        ]);

        // Fire payment received event
        event(new PaymentReceived($payment));

        Log::info('Manual invoice payment recorded', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'amount' => $amount,
            'collected_by' => $collectedBy,
            'receipt_number' => $receiptNumber,
        ]);

        return $payment;
    }

    /**
     * Record wallet top-up payment for prepaid customers.
     */
    protected function recordWalletTopupPayment(
        array $data,
        $customer,
        int $collectedBy,
        $collectionDate
    ): Payment {
        $amount = $data['amount'];
        $notes = $data['notes'] ?? '';
        $receiptNumber = $data['receipt_number'] ?? '';
        $receiptPath = $data['receipt_path'] ?? null;
        $location = $data['location'] ?? '';

        // Create the payment record
        $payment = Payment::create([
            'tenant_id' => $customer->tenant_id,
            'uuid' => (string) Str::orderedUuid(),
            'invoice_id' => null,
            'customer_id' => $customer->id,
            'reseller_id' => $customer->reseller_id,
            'amount' => $amount,
            'fee_amount' => 0,
            'net_amount' => $amount,
            'method' => PaymentMethod::CASH,
            'status' => PaymentStatus::COMPLETED,
            'gateway_reference' => $receiptNumber,
            'gateway_response' => null,
            'paid_at' => $collectionDate,
            'notes' => $notes . ' | Wallet top-up' . ($location ? " | Collected at: {$location}" : ''),
            'receipt_path' => $receiptPath,
            'collected_by' => $collectedBy,
            'created_by' => $collectedBy,
            'updated_by' => $collectedBy,
        ]);

        // Top up the wallet using PrepaidService
        $this->prepaidService->topUpWallet($payment);

        Log::info('Manual wallet top-up payment recorded', [
            'payment_id' => $payment->id,
            'customer_id' => $customer->id,
            'amount' => $amount,
            'collected_by' => $collectedBy,
            'receipt_number' => $receiptNumber,
            'new_balance' => $customer->fresh()->wallet_balance,
        ]);

        return $payment;
    }

    /**
     * Record a payment that will be allocated across multiple invoices (oldest first).
     * This handles the case where a customer pays a single amount to cover multiple outstanding invoices.
     */
    public function recordMultiInvoicePayment(array $data): Payment
    {
        DB::beginTransaction();

        try {
            $this->validateData($data);

            $customerId = $data['customer_id'];
            $amount = $data['amount'];
            $collectedBy = $data['collected_by'];
            $collectionDate = $data['collection_date'] ?? now();

            $customer = \App\Models\Customer::findOrFail($customerId);

            // Get all outstanding invoices for this customer, ordered by due date (oldest first)
            $outstandingInvoices = Invoice::where('customer_id', $customer->id)
                ->where('status', '!=', 'paid')
                ->where('outstanding_amount', '>', 0)
                ->orderBy('due_date', 'asc')
                ->with('package')
                ->get();

            if ($outstandingInvoices->isEmpty()) {
                throw new \Exception('No outstanding invoices found for this customer');
            }

            // Create the payment record
            $payment = Payment::create([
                'tenant_id' => $customer->tenant_id,
                'uuid' => (string) Str::orderedUuid(),
                'invoice_id' => null, // Will be null for multi-invoice payments
                'customer_id' => $customer->id,
                'reseller_id' => $customer->reseller_id,
                'amount' => $amount,
                'fee_amount' => 0,
                'net_amount' => $amount,
                'method' => PaymentMethod::CASH,
                'status' => PaymentStatus::COMPLETED,
                'gateway_reference' => $data['receipt_number'] ?? '',
                'gateway_response' => null,
                'paid_at' => $collectionDate,
                'notes' => ($data['notes'] ?? '') . ($data['location'] ? " | Collected at: {$data['location']}" : ''),
                'receipt_path' => $data['receipt_path'] ?? null,
                'collected_by' => $collectedBy,
                'created_by' => $collectedBy,
                'updated_by' => $collectedBy,
            ]);

            // Allocate the payment across invoices (oldest first)
            $remainingAmount = $amount;
            $allocations = [];

            foreach ($outstandingInvoices as $invoice) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $invoiceOutstanding = $invoice->outstanding_amount;
                $allocatedAmount = min($remainingAmount, $invoiceOutstanding);

                if ($allocatedAmount > 0) {
                    $allocations[] = [
                        'invoice_id' => $invoice->id,
                        'amount' => $allocatedAmount,
                    ];

                    // Update the invoice
                    $invoice->update([
                        'paid_amount' => DB::raw('paid_amount + ' . $allocatedAmount),
                        'outstanding_amount' => DB::raw('outstanding_amount - ' . $allocatedAmount),
                        'status' => $invoice->outstanding_amount - $allocatedAmount <= 0 ? 'paid' : 'partial',
                        'paid_at' => $collectionDate,
                        'updated_by' => $collectedBy,
                    ]);

                    $remainingAmount -= $allocatedAmount;

                    // Fire payment received event for each allocation
                    // Create a payment record for each invoice allocation
                    $allocationPayment = Payment::create([
                        'tenant_id' => $customer->tenant_id,
                        'uuid' => (string) Str::orderedUuid(),
                        'invoice_id' => $invoice->id,
                        'customer_id' => $customer->id,
                        'reseller_id' => $customer->reseller_id,
                        'amount' => $allocatedAmount,
                        'fee_amount' => 0,
                        'net_amount' => $allocatedAmount,
                        'method' => PaymentMethod::CASH,
                        'status' => PaymentStatus::COMPLETED,
                        'gateway_reference' => $payment->gateway_reference . '_' . $invoice->id,
                        'gateway_response' => null,
                        'paid_at' => $collectionDate,
                        'notes' => 'Allocated from multi-invoice payment ' . $payment->uuid . ' | ' . ($data['notes'] ?? ''),
                        'receipt_path' => $data['receipt_path'] ?? null,
                        'collected_by' => $collectedBy,
                        'created_by' => $collectedBy,
                        'updated_by' => $collectedBy,
                        'split_from_payment_id' => $payment->id,
                        'is_partial' => true,
                    ]);

                    event(new PaymentReceived($allocationPayment));
                }
            }

            // Update the main payment record with allocation info
            $payment->update([
                'notes' => $payment->notes . ' | Allocated to ' . count($allocations) . ' invoice(s)',
            ]);

            DB::commit();

            Log::info('Multi-invoice manual payment recorded', [
                'payment_id' => $payment->id,
                'customer_id' => $customer->id,
                'total_amount' => $amount,
                'collected_by' => $collectedBy,
                'allocations' => $allocations,
                'remaining_amount' => $remainingAmount,
            ]);

            return $payment;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to record multi-invoice manual payment', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Get the list of customers with outstanding invoices for a field agent.
     * Useful for field agents to see which customers they can collect from.
     */
    public function getOutstandingCustomers(int $fieldAgentId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        // Get customers assigned to this field agent
        $customers = \App\Models\Customer::where('assigned_to', $fieldAgentId)
            ->with(['subscriptions', 'invoices' => function ($query) {
                $query->where('status', '!=', 'paid')
                    ->where('outstanding_amount', '>', 0)
                    ->orderBy('due_date', 'asc');
            }])
            ->whereHas('invoices', function ($query) {
                $query->where('status', '!=', 'paid')
                    ->where('outstanding_amount', '>', 0);
            })
            ->limit($limit)
            ->get();

        return $customers;
    }

    /**
     * Generate a receipt number for manual payments.
     */
    public function generateReceiptNumber(int $tenantId): string
    {
        $datePart = now()->format('Ymd');
        $tenantPrefix = 'T' . str_pad($tenantId, 4, '0', STR_PAD_LEFT);

        // Get the last receipt number for today
        $lastPayment = Payment::where('tenant_id', $tenantId)
            ->where('gateway_reference', 'LIKE', "{$tenantPrefix}{$datePart}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastPayment) {
            $lastNumber = substr($lastPayment->gateway_reference, -4);
            $newNumber = str_pad((int)$lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $tenantPrefix . $datePart . $newNumber;
    }
}
