<?php

namespace App\Http\Controllers\Api\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Requests\Api\Payments\WalletTopUpRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\Billing\PrepaidService;
use App\Services\Payments\BkashService;
use App\Services\Payments\IdempotencyService;
use App\Services\Payments\NagadService;
use App\Services\Payments\SSLCommerzService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Controller for wallet top-up operations.
 * Allows customers and staff to add funds to customer wallets.
 */
class WalletTopUpController extends Controller
{
    protected PrepaidService $prepaidService;
    protected IdempotencyService $idempotencyService;

    public function __construct(PrepaidService $prepaidService, IdempotencyService $idempotencyService)
    {
        $this->prepaidService = $prepaidService;
        $this->idempotencyService = $idempotencyService;
    }

    /**
     * Get wallet balance for a customer.
     *
     * @param int $customerId
     * @return JsonResponse
     */
    public function getBalance(int $customerId): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($customerId);

            return response()->json([
                'success' => true,
                'customer_id' => $customer->id,
                'balance_poysha' => $customer->wallet_balance,
                'balance_bdt' => number_format($customer->wallet_balance / 100, 2),
                'currency' => 'BDT',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get wallet balance for the authenticated customer.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMyBalance(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'No customer found for this user',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'customer_id' => $customer->id,
            'balance_poysha' => $customer->wallet_balance,
            'balance_bdt' => number_format($customer->wallet_balance / 100, 2),
            'currency' => 'BDT',
        ]);
    }

    /**
     * Initiate a wallet top-up via a payment gateway.
     * This creates a payment record and redirects to the gateway.
     *
     * @param WalletTopUpRequest $request
     * @return JsonResponse
     */
    public function initiateTopUp(WalletTopUpRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();
            $customer = $request->user()->customer;
            $user = $request->user();

            if (!$customer) {
                throw new \Exception('No customer found for this user');
            }

            // Check if this is a prepaid customer
            $subscription = $customer->subscriptions()->active()->first();
            if (!$subscription || $subscription->billing_type !== 'prepaid') {
                throw new \Exception('Only prepaid customers can top up their wallet');
            }

            $gateway = $data['gateway'] ?? PaymentMethod::SSLCOMMERZ->value;
            $amount = $data['amount']; // Already in poysha from request preparation
            $idempotencyKey = $data['idempotency_key'] ?? null;

            // Generate a unique reference for this top-up
            $topUpReference = 'WALLET_' . $customer->id . '_' . now()->format('YmdHis') . '_' . Str::random(6);

            // Create a pending payment record for the top-up
            $payment = Payment::create([
                'tenant_id' => $customer->tenant_id,
                'uuid' => (string) Str::orderedUuid(),
                'invoice_id' => null,
                'customer_id' => $customer->id,
                'reseller_id' => $customer->reseller_id,
                'amount' => $amount,
                'fee_amount' => 0,
                'net_amount' => $amount,
                'method' => $gateway,
                'status' => PaymentStatus::PENDING,
                'gateway_reference' => $topUpReference,
                'gateway_response' => null,
                'paid_at' => null,
                'notes' => 'Wallet top-up initiation via ' . ucfirst($gateway),
                'receipt_path' => null,
                'collected_by' => null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'is_wallet_topup' => true,
                'applied_to_invoice' => false,
            ]);

            // Get the appropriate gateway service
            $gatewayService = $this->getGatewayService($gateway);

            if (!$gatewayService) {
                throw new \Exception('Invalid gateway specified');
            }

            // Prepare data for gateway
            $gatewayData = [
                'amount' => $amount,
                'invoice_number' => $topUpReference,
                'customer_name' => $customer->full_name,
                'customer_email' => $customer->email ?? $user->email,
                'customer_phone' => $customer->phone ?? '',
                'idempotency_key' => $idempotencyKey,
                'callback_url' => route('api.webhooks.' . $gateway),
                'product_name' => 'Wallet Top-up',
                'value_a' => 'wallet_topup',
                'value_b' => $customer->id,
                'value_c' => $payment->id,
            ];

            // Initiate payment with the gateway
            $gatewayResponse = $gatewayService->initiatePayment($gatewayData);

            // Update payment with gateway response
            $payment->update([
                'gateway_reference' => $gatewayResponse['gateway_reference'] ?? $gatewayResponse['transaction_id'],
                'gateway_response' => json_encode($gatewayResponse),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Wallet top-up initiated successfully',
                'payment_id' => $payment->id,
                'gateway' => $gateway,
                'amount_bdt' => number_format($amount / 100, 2),
                'redirect_url' => $gatewayResponse['redirect_url'],
                'gateway_reference' => $gatewayResponse['gateway_reference'] ?? $gatewayResponse['transaction_id'],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Wallet top-up initiation failed', [
                'customer_id' => $customer->id ?? null,
                'amount' => $amount ?? null,
                'gateway' => $gateway ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get the appropriate gateway service.
     */
    protected function getGatewayService(string $gateway)
    {
        return match ($gateway) {
            PaymentMethod::BKASH->value => app(BkashService::class),
            PaymentMethod::NAGAD->value => app(NagadService::class),
            PaymentMethod::SSLCOMMERZ->value => app(SSLCommerzService::class),
            default => null,
        };
    }

    /**
     * Get wallet transaction history for a customer.
     *
     * @param int $customerId
     * @return JsonResponse
     */
    public function getTransactionHistory(int $customerId, int $limit = 50): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($customerId);

            $transactions = \App\Models\WalletTransaction::where('customer_id', $customer->id)
                ->with(['createdBy', 'payment'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'customer_id' => $customer->id,
                'transactions' => $transactions,
                'current_balance_poysha' => $customer->wallet_balance,
                'current_balance_bdt' => number_format($customer->wallet_balance / 100, 2),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get minimum balance requirements for service.
     *
     * @return JsonResponse
     */
    public function getMinimumBalance(): JsonResponse
    {
        $minBalance = config('billing.prepaid.min_balance', 0);
        $suspendThreshold = config('billing.prepaid.suspend_threshold', 0);

        return response()->json([
            'success' => true,
            'min_balance_poysha' => $minBalance,
            'min_balance_bdt' => number_format($minBalance / 100, 2),
            'suspend_threshold_poysha' => $suspendThreshold,
            'suspend_threshold_bdt' => number_format($suspendThreshold / 100, 2),
            'currency' => 'BDT',
        ]);
    }
}
