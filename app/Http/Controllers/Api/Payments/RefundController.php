<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Requests\Api\Payments\RefundRequest;
use App\Models\Payment;
use App\Services\Payments\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for processing payment refunds.
 */
class RefundController extends Controller
{
    protected RefundService $refundService;

    public function __construct(RefundService $refundService)
    {
        $this->refundService = $refundService;
    }

    /**
     * Check if a payment can be refunded.
     *
     * @param int $paymentId
     * @return JsonResponse
     */
    public function checkRefundEligibility(int $paymentId): JsonResponse
    {
        try {
            $payment = Payment::findOrFail($paymentId);
            $canRefund = $this->refundService->canRefund($payment);
            
            return response()->json([
                'success' => true,
                'can_refund' => $canRefund,
                'payment' => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'method' => $payment->method,
                    'invoice_id' => $payment->invoice_id,
                    'is_wallet_topup' => $payment->is_wallet_topup,
                ],
                'max_refund_amount' => $canRefund ? $payment->amount : 0,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Process a refund for a payment.
     *
     * @param RefundRequest $request
     * @param int $paymentId
     * @return JsonResponse
     */
    public function processRefund(RefundRequest $request, int $paymentId): JsonResponse
    {
        try {
            $payment = Payment::findOrFail($paymentId);
            
            if (!$this->refundService->canRefund($payment)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This payment cannot be refunded',
                ], 422);
            }

            $data = $request->validated();
            $processedBy = $request->user()->id;
            $idempotencyKey = $data['idempotency_key'] ?? null;
            
            $result = $this->refundService->processRefund(
                $payment,
                $data['amount'],
                $data['reason'],
                $processedBy,
                $idempotencyKey
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result,
            ], $result['success'] ? 201 : 400);

        } catch (\Exception $e) {
            Log::error('Refund processing failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Process a manual refund (without original payment).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function processManualRefund(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'invoice_id' => ['nullable', 'exists:invoices,id'],
        ]);

        try {
            $processedBy = $request->user()->id;
            
            $result = $this->refundService->processManualRefund(
                $data['customer_id'],
                $data['amount'],
                $data['reason'],
                $processedBy,
                $data['invoice_id'] ?? null
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result,
            ], $result['success'] ? 201 : 400);

        } catch (\Exception $e) {
            Log::error('Manual refund failed', [
                'customer_id' => $data['customer_id'] ?? null,
                'amount' => $data['amount'] ?? null,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get refund history for a customer.
     *
     * @param int $customerId
     * @return JsonResponse
     */
    public function getCustomerRefunds(int $customerId): JsonResponse
    {
        try {
            $refunds = $this->refundService->getCustomerRefundHistory($customerId);
            
            return response()->json([
                'success' => true,
                'data' => $refunds,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get customer refunds', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
