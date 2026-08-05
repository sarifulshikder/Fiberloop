<?php

namespace App\Http\Controllers\Api\Payments;

use App\Http\Requests\Api\Payments\ManualPaymentRequest;
use App\Services\Payments\ManualPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for manual/cash payment entry by field agents.
 */
class ManualPaymentController extends Controller
{
    protected ManualPaymentService $manualPaymentService;

    public function __construct(ManualPaymentService $manualPaymentService)
    {
        $this->manualPaymentService = $manualPaymentService;
    }

    /**
     * Get customers with outstanding invoices for the current field agent.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getOutstandingCustomers(Request $request): JsonResponse
    {
        $fieldAgentId = $request->user()->id;
        $limit = $request->get('limit', 50);
        
        try {
            $customers = $this->manualPaymentService->getOutstandingCustomers($fieldAgentId, $limit);
            
            return response()->json([
                'success' => true,
                'data' => $customers,
                'message' => 'Outstanding customers retrieved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get outstanding customers', [
                'error' => $e->getMessage(),
                'field_agent_id' => $fieldAgentId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Record a manual/cash payment.
     *
     * @param ManualPaymentRequest $request
     * @return JsonResponse
     */
    public function recordPayment(ManualPaymentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $fieldAgentId = $request->user()->id;
        
        // Add the collected_by field from the authenticated user
        $data['collected_by'] = $fieldAgentId;
        
        // Generate receipt number if not provided
        if (empty($data['receipt_number'])) {
            $data['receipt_number'] = $this->manualPaymentService->generateReceiptNumber(
                $request->user()->tenant_id ?? 1
            );
        }

        try {
            $payment = $this->manualPaymentService->recordPayment($data);
            
            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Manual payment recorded successfully',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to record manual payment', [
                'error' => $e->getMessage(),
                'data' => $data,
                'field_agent_id' => $fieldAgentId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Record a multi-invoice payment (payment allocated across multiple invoices).
     *
     * @param ManualPaymentRequest $request
     * @return JsonResponse
     */
    public function recordMultiInvoicePayment(ManualPaymentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $fieldAgentId = $request->user()->id;
        
        // Add the collected_by field from the authenticated user
        $data['collected_by'] = $fieldAgentId;
        
        // Generate receipt number if not provided
        if (empty($data['receipt_number'])) {
            $data['receipt_number'] = $this->manualPaymentService->generateReceiptNumber(
                $request->user()->tenant_id ?? 1
            );
        }
        
        // Force multi-invoice mode by removing invoice_id
        unset($data['invoice_id']);
        $data['is_multi_invoice'] = true;

        try {
            $payment = $this->manualPaymentService->recordMultiInvoicePayment($data);
            
            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Multi-invoice payment recorded and allocated successfully',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to record multi-invoice payment', [
                'error' => $e->getMessage(),
                'data' => $data,
                'field_agent_id' => $fieldAgentId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate a receipt number for manual payment.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateReceiptNumber(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id ?? 1;
        
        try {
            $receiptNumber = $this->manualPaymentService->generateReceiptNumber($tenantId);
            
            return response()->json([
                'success' => true,
                'receipt_number' => $receiptNumber,
                'message' => 'Receipt number generated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate receipt number', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
