<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payments\BkashService;
use App\Services\Payments\NagadService;
use App\Services\Payments\PaymentGatewayContract;
use App\Services\Payments\SSLCommerzService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PayNowController extends Controller
{
    /**
     * Get payment options for the authenticated customer.
     */
    public function getPaymentOptions(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        // Get outstanding invoices
        $invoices = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['draft', 'sent', 'overdue', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->orderBy('due_date', 'asc')
            ->get();

        $totalOutstanding = $invoices->sum('outstanding_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'customer_id' => $customer->id,
                'customer_name' => $customer->full_name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'outstanding_invoices' => \App\Http\Resources\Api\InvoiceResource::collection($invoices),
                'total_outstanding_amount' => $totalOutstanding / 100, // Convert to BDT
                'total_outstanding_amount_formatted' => 'BDT ' . number_format($totalOutstanding / 100, 2),
                'wallet_balance' => $customer->wallet_balance / 100,
                'wallet_balance_formatted' => 'BDT ' . number_format($customer->wallet_balance / 100, 2),
                'payment_gateways' => [
                    [
                        'id' => 'bkash',
                        'name' => 'bKash',
                        'code' => 'bkash',
                        'logo' => null,
                        'is_active' => true,
                    ],
                    [
                        'id' => 'nagad',
                        'name' => 'Nagad',
                        'code' => 'nagad',
                        'logo' => null,
                        'is_active' => true,
                    ],
                    [
                        'id' => 'sslcommerz',
                        'name' => 'SSLCommerz',
                        'code' => 'sslcommerz',
                        'logo' => null,
                        'is_active' => true,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Initiate a payment for an invoice.
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'invoice_id' => 'required|exists:invoices,id',
            'gateway' => 'required|in:bkash,nagad,sslcommerz',
            'amount' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = $this->getAuthenticatedCustomer($request);
        $invoice = Invoice::where('customer_id', $customer->id)
            ->findOrFail($request->invoice_id);

        // Verify customer owns the invoice
        if ($invoice->customer_id !== $customer->id) {
            abort(403, 'Unauthorized access to this invoice.');
        }

        // Verify amount doesn't exceed outstanding
        if ($request->amount > $invoice->outstanding_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Payment amount exceeds outstanding balance.',
                'data' => [
                    'outstanding_amount' => $invoice->outstanding_amount / 100,
                    'requested_amount' => $request->amount / 100,
                ],
            ], 400);
        }

        $gatewayService = $this->getGatewayService($request->gateway);

        $paymentData = [
            'customer_id' => $customer->id,
            'customer_name' => $customer->full_name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'amount' => $request->amount, // Already in poysha
            'currency' => 'BDT',
            'order_id' => 'INV-' . $invoice->invoice_number . '-' . time(),
            'callback_url' => route('api.webhooks.' . $request->gateway),
            'success_url' => config('app.url') . '/customer/payment/success?invoice_id=' . $invoice->uuid,
            'fail_url' => config('app.url') . '/customer/payment/fail?invoice_id=' . $invoice->uuid,
        ];

        try {
            $response = $gatewayService->initiatePayment($paymentData);

            // Create a pending payment record
            $payment = Payment::create([
                'tenant_id' => $customer->tenant_id,
                'uuid' => (string) \Str::orderedUuid(),
                'invoice_id' => $invoice->id,
                'customer_id' => $customer->id,
                'amount' => $request->amount,
                'net_amount' => $request->amount,
                'method' => $this->mapGatewayToMethod($request->gateway),
                'status' => 'pending',
                'gateway_reference' => $response['transaction_id'] ?? null,
                'gateway_response' => json_encode($response),
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully',
                'data' => [
                    'payment_id' => $payment->uuid,
                    'gateway' => $request->gateway,
                    'redirect_url' => $response['redirect_url'] ?? null,
                    'transaction_id' => $response['transaction_id'] ?? null,
                    'amount' => $request->amount / 100,
                    'amount_formatted' => 'BDT ' . number_format($request->amount / 100, 2),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment status.
     */
    public function getPaymentStatus(Request $request, Payment $payment): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        // Verify customer owns the payment
        if ($payment->customer_id !== $customer->id) {
            abort(403, 'Unauthorized access to this payment.');
        }

        $payment->load(['invoice', 'customer']);

        return response()->json([
            'success' => true,
            'data' => new \App\Http\Resources\Api\PaymentResource($payment),
        ]);
    }

    /**
     * Get payment history for the authenticated customer.
     */
    public function history(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);

        $payments = Payment::where('customer_id', $customer->id)
            ->orderBy('paid_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\Api\PaymentResource::collection($payments),
        ]);
    }

    /**
     * Get the gateway service instance.
     */
    protected function getGatewayService(string $gateway): PaymentGatewayContract
    {
        return match ($gateway) {
            'bkash' => new BkashService(),
            'nagad' => new NagadService(),
            'sslcommerz' => new SSLCommerzService(),
            default => throw new \InvalidArgumentException("Unsupported payment gateway: {$gateway}"),
        };
    }

    /**
     * Map gateway name to payment method enum value.
     */
    protected function mapGatewayToMethod(string $gateway): string
    {
        return match ($gateway) {
            'bkash' => 'bkash',
            'nagad' => 'nagad',
            'sslcommerz' => 'sslcommerz',
            default => 'other',
        };
    }

    /**
     * Get the authenticated customer from the request.
     */
    protected function getAuthenticatedCustomer(Request $request): Customer
    {
        $user = $request->user();

        if ($user->customer) {
            return $user->customer;
        }

        $customer = Customer::where('email', $user->email)->first();

        if (!$customer) {
            abort(403, 'Customer not found for authenticated user.');
        }

        return $customer;
    }
}
