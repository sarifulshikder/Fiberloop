<?php

namespace App\Http\Controllers\Api\Payments;

use App\Enums\PaymentStatus;
use App\Events\Billing\PaymentReceived;
use App\Models\Payment;
use App\Models\Invoice;
use App\Services\Payments\BkashService;
use App\Services\Payments\NagadService;
use App\Services\Payments\SSLCommerzService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Base webhook controller for payment gateways.
 * Handles incoming webhooks from all payment gateways.
 */
class WebhookController extends Controller
{
    /**
     * Handle bKash webhook.
     */
    public function handleBkash(Request $request, BkashService $bkashService): JsonResponse
    {
        $payload = $request->all();
        
        try {
            $this->processWebhook('bkash', $payload, $bkashService);
            
            return response()->json(['status' => 'success', 'message' => 'Webhook processed']);
        } catch (\Exception $e) {
            Log::error('bKash webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Handle Nagad webhook.
     */
    public function handleNagad(Request $request, NagadService $nagadService): JsonResponse
    {
        $payload = $request->all();
        
        try {
            $this->processWebhook('nagad', $payload, $nagadService);
            
            return response()->json(['status' => 'success', 'message' => 'Webhook processed']);
        } catch (\Exception $e) {
            Log::error('Nagad webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Handle SSLCommerz webhook.
     */
    public function handleSSLCommerz(Request $request, SSLCommerzService $sslCommerzService): JsonResponse
    {
        $payload = $request->all();
        
        try {
            $this->processWebhook('sslcommerz', $payload, $sslCommerzService);
            
            return response()->json(['status' => 'success', 'message' => 'Webhook processed']);
        } catch (\Exception $e) {
            Log::error('SSLCommerz webhook processing failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Process webhook from a gateway.
     */
    protected function processWebhook(string $gateway, array $payload, $gatewayService): void
    {
        // Extract transaction ID - different gateways use different field names
        $transactionId = $this->extractTransactionId($gateway, $payload);
        
        if (empty($transactionId)) {
            throw new \Exception('Cannot extract transaction ID from webhook payload');
        }

        // Check for duplicate webhook delivery
        $existingPayment = Payment::where('gateway_reference', $transactionId)
            ->where('method', $gateway)
            ->first();
        
        if ($existingPayment) {
            Log::info("Duplicate webhook received for transaction {$transactionId}", [
                'gateway' => $gateway,
                'payment_id' => $existingPayment->id,
            ]);
            return; // Already processed, skip to prevent double-crediting
        }

        // Process the webhook through the gateway service
        $gatewayResponse = $gatewayService->handleWebhook($payload);
        
        if ($gatewayResponse['status'] !== 'completed') {
            Log::info("Webhook received but payment not completed", [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'status' => $gatewayResponse['status'],
            ]);
            return;
        }

        // Find the invoice associated with this payment
        $invoiceNumber = $this->extractInvoiceNumber($gateway, $payload);
        $invoice = null;
        
        if ($invoiceNumber) {
            $invoice = Invoice::where('invoice_number', $invoiceNumber)
                ->where('status', '!=', 'paid')
                ->first();
        }

        // If no invoice found by invoice number, try to find by transaction ID
        if (!$invoice) {
            $invoice = Invoice::where('gateway_reference', $transactionId)
                ->where('status', '!=', 'paid')
                ->first();
        }

        // If we still can't find an invoice, log and return
        if (!$invoice) {
            Log::warning("Cannot find invoice for webhook payment", [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'invoice_number' => $invoiceNumber,
            ]);
            return;
        }

        // Process the payment
        $this->processPayment($invoice, $gateway, $gatewayResponse, $payload);
    }

    /**
     * Extract transaction ID from payload based on gateway.
     */
    protected function extractTransactionId(string $gateway, array $payload): string
    {
        switch ($gateway) {
            case 'bkash':
                return $payload['paymentID'] ?? $payload['transaction_id'] ?? '';
            case 'nagad':
                return $payload['paymentReference'] ?? $payload['transaction_id'] ?? '';
            case 'sslcommerz':
                return $payload['tran_id'] ?? $payload['transaction_id'] ?? '';
            default:
                return $payload['transaction_id'] ?? '';
        }
    }

    /**
     * Extract invoice number from payload based on gateway.
     */
    protected function extractInvoiceNumber(string $gateway, array $payload): string
    {
        switch ($gateway) {
            case 'bkash':
                return $payload['merchantInvoiceNumber'] ?? $payload['order_id'] ?? '';
            case 'nagad':
                return $payload['merchantInvoiceNumber'] ?? $payload['order_id'] ?? '';
            case 'sslcommerz':
                return $payload['tran_id'] ?? ''; // SSLCommerz typically uses tran_id as the invoice number
            default:
                return $payload['invoice_number'] ?? '';
        }
    }

    /**
     * Process the payment and create records.
     */
    protected function processPayment($invoice, string $gateway, array $gatewayResponse, array $rawPayload): void
    {
        DB::transaction(function () use ($invoice, $gateway, $gatewayResponse, $rawPayload) {
            // Create the payment record
            $payment = Payment::create([
                'tenant_id' => $invoice->tenant_id,
                'uuid' => (string) \Str::orderedUuid(),
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'amount' => $gatewayResponse['amount'],
                'fee_amount' => 0, // Gateway fees can be calculated if available
                'net_amount' => $gatewayResponse['amount'],
                'method' => $gateway,
                'status' => PaymentStatus::COMPLETED,
                'gateway_reference' => $gatewayResponse['gateway_reference'] ?? $gatewayResponse['transaction_id'],
                'gateway_response' => $gatewayResponse['gateway_response'] ?? json_encode($rawPayload),
                'paid_at' => $gatewayResponse['paid_at'] ?? now(),
                'notes' => 'Payment received via ' . ucfirst($gateway) . ' webhook',
                'created_by' => 1, // System user
                'updated_by' => 1,
            ]);

            // Update the invoice
            $invoice->update([
                'paid_amount' => DB::raw('paid_amount + ' . $gatewayResponse['amount']),
                'outstanding_amount' => DB::raw('outstanding_amount - ' . $gatewayResponse['amount']),
                'status' => $invoice->outstanding_amount <= 0 ? 'paid' : 'partial',
                'paid_at' => $gatewayResponse['paid_at'] ?? now(),
                'gateway_reference' => $gatewayResponse['gateway_reference'] ?? $gatewayResponse['transaction_id'],
                'updated_by' => 1,
            ]);

            // Fire payment received event
            event(new PaymentReceived($payment));

            Log::info("Payment processed successfully", [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $gatewayResponse['amount'],
                'gateway' => $gateway,
                'gateway_reference' => $gatewayResponse['gateway_reference'],
            ]);
        });
    }
}
