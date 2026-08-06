<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\ReconciliationStatus;
use App\Models\Payment;
use App\Models\PaymentReconciliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for reconciling payments with gateway settlement reports.
 * Identifies discrepancies between recorded payments and gateway settlements.
 */
class PaymentReconciliationService
{
    protected BkashService $bkashService;
    protected NagadService $nagadService;
    protected SSLCommerzService $sslCommerzService;

    public function __construct(
        BkashService $bkashService,
        NagadService $nagadService,
        SSLCommerzService $sslCommerzService
    ) {
        $this->bkashService = $bkashService;
        $this->nagadService = $nagadService;
        $this->sslCommerzService = $sslCommerzService;
    }

    /**
     * Get the appropriate gateway service based on payment method.
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
     * Reconcile payments for a specific gateway and date range.
     *
     * @param string $gateway Gateway name (bkash, nagad, sslcommerz)
     * @param string $startDate Start date for reconciliation
     * @param string $endDate End date for reconciliation
     * @param int $tenantId Tenant ID
     * @return array Results of reconciliation
     */
    public function reconcile(string $gateway, string $startDate, string $endDate, int $tenantId): array
    {
        DB::beginTransaction();

        try {
            // Validate gateway
            if (!in_array($gateway, [PaymentMethod::BKASH->value, PaymentMethod::NAGAD->value, PaymentMethod::SSLCOMMERZ->value])) {
                throw new \Exception('Invalid gateway specified for reconciliation');
            }

            $gatewayService = $this->getGatewayService($gateway);

            if (!$gatewayService) {
                throw new \Exception('Gateway service not available');
            }

            // Step 1: Fetch settlement report from the gateway
            $settlementReport = $this->fetchSettlementReport($gatewayService, $startDate, $endDate);

            if (empty($settlementReport)) {
                Log::info("No settlement report data from {$gateway} for period {$startDate} to {$endDate}");

                DB::commit();
                return [
                    'status' => 'success',
                    'gateway' => $gateway,
                    'period' => "{$startDate} to {$endDate}",
                    'total_settlements' => 0,
                    'total_recorded' => 0,
                    'matched' => 0,
                    'discrepancies' => 0,
                    'message' => 'No settlement data available for this period',
                ];
            }

            // Step 2: Get recorded payments for this gateway and period
            $recordedPayments = Payment::where('tenant_id', $tenantId)
                ->where('method', $gateway)
                ->where('status', 'completed')
                ->whereDate('paid_at', '>=', $startDate)
                ->whereDate('paid_at', '<=', $endDate)
                ->with('invoice', 'customer')
                ->get();

            // Step 3: Match settlements with recorded payments
            $results = [
                'total_settlements' => count($settlementReport),
                'total_recorded' => $recordedPayments->count(),
                'matched' => 0,
                'discrepancies' => [],
                'matched_payments' => [],
            ];

            $matchedGatewayReferences = [];

            foreach ($recordedPayments as $payment) {
                $gatewayReference = $payment->gateway_reference;

                if (empty($gatewayReference)) {
                    // Payment without gateway reference - manual or issue
                    $results['discrepancies'][] = [
                        'type' => 'missing_reference',
                        'payment_id' => $payment->id,
                        'amount' => $payment->amount,
                        'customer_id' => $payment->customer_id,
                        'invoice_number' => $payment->invoice?->invoice_number ?? null,
                        'message' => 'Payment recorded but missing gateway reference',
                    ];
                    continue;
                }

                // Look for matching settlement
                $matchingSettlement = null;
                foreach ($settlementReport as $settlement) {
                    if ($this->isMatch($gateway, $settlement, $payment)) {
                        $matchingSettlement = $settlement;
                        $matchedGatewayReferences[] = $gatewayReference;
                        break;
                    }
                }

                if ($matchingSettlement) {
                    // Check amount match
                    $settlementAmount = $this->extractSettlementAmount($gateway, $matchingSettlement);
                    $paymentAmount = $payment->amount;

                    if ($settlementAmount === $paymentAmount) {
                        // Perfect match
                        $results['matched']++;
                        $results['matched_payments'][] = [
                            'payment_id' => $payment->id,
                            'gateway_reference' => $gatewayReference,
                            'amount' => $paymentAmount,
                            'customer_id' => $payment->customer_id,
                            'settlement_amount' => $settlementAmount,
                            'status' => 'matched',
                        ];

                        // Record reconciliation
                        PaymentReconciliation::create([
                            'tenant_id' => $tenantId,
                            'payment_id' => $payment->id,
                            'gateway' => $gateway,
                            'gateway_reference' => $gatewayReference,
                            'recorded_amount' => $paymentAmount,
                            'settlement_amount' => $settlementAmount,
                            'settlement_date' => $this->extractSettlementDate($gateway, $matchingSettlement),
                            'status' => ReconciliationStatus::MATCHED,
                            'notes' => 'Amount and reference matched',
                            'settlement_data' => json_encode($matchingSettlement),
                        ]);
                    } else {
                        // Amount mismatch
                        $results['discrepancies'][] = [
                            'type' => 'amount_mismatch',
                            'payment_id' => $payment->id,
                            'gateway_reference' => $gatewayReference,
                            'recorded_amount' => $paymentAmount,
                            'settlement_amount' => $settlementAmount,
                            'customer_id' => $payment->customer_id,
                            'message' => 'Amount mismatch between recorded payment and settlement',
                        ];

                        // Record reconciliation
                        PaymentReconciliation::create([
                            'tenant_id' => $tenantId,
                            'payment_id' => $payment->id,
                            'gateway' => $gateway,
                            'gateway_reference' => $gatewayReference,
                            'recorded_amount' => $paymentAmount,
                            'settlement_amount' => $settlementAmount,
                            'settlement_date' => $this->extractSettlementDate($gateway, $matchingSettlement),
                            'status' => ReconciliationStatus::DISCREPANCY,
                            'notes' => 'Amount mismatch: recorded ' . $paymentAmount . ' vs settlement ' . $settlementAmount,
                            'settlement_data' => json_encode($matchingSettlement),
                        ]);
                    }
                } else {
                    // No matching settlement found
                    $results['discrepancies'][] = [
                        'type' => 'no_settlement_match',
                        'payment_id' => $payment->id,
                        'gateway_reference' => $gatewayReference,
                        'amount' => $payment->amount,
                        'customer_id' => $payment->customer_id,
                        'message' => 'No matching settlement found for this payment',
                    ];

                    // Record reconciliation
                    PaymentReconciliation::create([
                        'tenant_id' => $tenantId,
                        'payment_id' => $payment->id,
                        'gateway' => $gateway,
                        'gateway_reference' => $gatewayReference,
                        'recorded_amount' => $payment->amount,
                        'settlement_amount' => 0,
                        'settlement_date' => null,
                        'status' => ReconciliationStatus::PENDING,
                        'notes' => 'No matching settlement found',
                        'settlement_data' => null,
                    ]);
                }
            }

            // Step 4: Check for settlements without matching payments
            foreach ($settlementReport as $settlement) {
                $gatewayReference = $this->extractGatewayReference($gateway, $settlement);

                if ($gatewayReference && !in_array($gatewayReference, $matchedGatewayReferences)) {
                    // Settlement without matching payment
                    $settlementAmount = $this->extractSettlementAmount($gateway, $settlement);
                    $settlementDate = $this->extractSettlementDate($gateway, $settlement);

                    $results['discrepancies'][] = [
                        'type' => 'orphaned_settlement',
                        'gateway_reference' => $gatewayReference,
                        'settlement_amount' => $settlementAmount,
                        'settlement_date' => $settlementDate,
                        'message' => 'Settlement found without matching recorded payment',
                    ];

                    // Record reconciliation
                    PaymentReconciliation::create([
                        'tenant_id' => $tenantId,
                        'payment_id' => null,
                        'gateway' => $gateway,
                        'gateway_reference' => $gatewayReference,
                        'recorded_amount' => 0,
                        'settlement_amount' => $settlementAmount,
                        'settlement_date' => $settlementDate,
                        'status' => ReconciliationStatus::DISCREPANCY,
                        'notes' => 'Settlement without matching payment record',
                        'settlement_data' => json_encode($settlement),
                    ]);
                }
            }

            DB::commit();

            return [
                'status' => 'success',
                'gateway' => $gateway,
                'period' => "{$startDate} to {$endDate}",
                'total_settlements' => $results['total_settlements'],
                'total_recorded' => $results['total_recorded'],
                'matched' => $results['matched'],
                'discrepancies' => $results['discrepancies'],
                'matched_payments' => $results['matched_payments'],
                'message' => 'Reconciliation completed',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Payment reconciliation failed for {$gateway}", [
                'error' => $e->getMessage(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'tenant_id' => $tenantId,
            ]);

            throw $e;
        }
    }

    /**
     * Fetch settlement report from a gateway.
     * This is a placeholder - actual implementation depends on gateway API.
     */
    protected function fetchSettlementReport($gatewayService, string $startDate, string $endDate): array
    {
        // Each gateway has different ways to fetch settlement reports
        // This is a placeholder implementation
        // In production, this would call the gateway's settlement/report API

        try {
            // Try to call the gateway-specific method if available
            if (method_exists($gatewayService, 'fetchSettlementReport')) {
                return $gatewayService->fetchSettlementReport($startDate, $endDate);
            }

            // Fallback: return empty array (simulate no settlement data)
            // In real implementation, this would be replaced with actual API call
            return [];

        } catch (\Exception $e) {
            Log::warning("Failed to fetch settlement report from gateway", [
                'gateway' => get_class($gatewayService),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if a settlement matches a payment.
     */
    protected function isMatch(string $gateway, array $settlement, Payment $payment): bool
    {
        $gatewayReference = $this->extractGatewayReference($gateway, $settlement);
        $paymentReference = $payment->gateway_reference;

        if (empty($gatewayReference) || empty($paymentReference)) {
            return false;
        }

        // Direct reference match
        if ($gatewayReference === $paymentReference) {
            return true;
        }

        // Some gateways might have different formats
        // Additional matching logic can be added here per gateway
        return false;
    }

    /**
     * Extract gateway reference from settlement data.
     */
    protected function extractGatewayReference(string $gateway, array $settlement): string
    {
        switch ($gateway) {
            case PaymentMethod::BKASH->value:
                return $settlement['paymentID'] ?? $settlement['tranId'] ?? '';
            case PaymentMethod::NAGAD->value:
                return $settlement['paymentReference'] ?? $settlement['tranId'] ?? '';
            case PaymentMethod::SSLCOMMERZ->value:
                return $settlement['tran_id'] ?? $settlement['transactionId'] ?? '';
            default:
                return $settlement['transactionId'] ?? $settlement['tranId'] ?? '';
        }
    }

    /**
     * Extract settlement amount from settlement data.
     */
    protected function extractSettlementAmount(string $gateway, array $settlement): int
    {
        // Convert from BDT to poysha if needed
        switch ($gateway) {
            case PaymentMethod::BKASH->value:
                $amount = $settlement['amount'] ?? $settlement['transactionAmount'] ?? 0;
                return $this->bdtToPoysha($amount);
            case PaymentMethod::NAGAD->value:
                $amount = $settlement['amount'] ?? $settlement['transactionAmount'] ?? 0;
                return $this->bdtToPoysha($amount);
            case PaymentMethod::SSLCOMMERZ->value:
                $amount = $settlement['amount'] ?? $settlement['store_amount'] ?? 0;
                return $this->bdtToPoysha($amount);
            default:
                $amount = $settlement['amount'] ?? 0;
                return $this->bdtToPoysha($amount);
        }
    }

    /**
     * Extract settlement date from settlement data.
     */
    protected function extractSettlementDate(string $gateway, array $settlement): ?string
    {
        switch ($gateway) {
            case PaymentMethod::BKASH->value:
                return $settlement['completedTime'] ?? $settlement['tranDate'] ?? null;
            case PaymentMethod::NAGAD->value:
                return $settlement['completedTime'] ?? $settlement['tranDate'] ?? null;
            case PaymentMethod::SSLCOMMERZ->value:
                return $settlement['tran_date'] ?? $settlement['transactionDate'] ?? null;
            default:
                return $settlement['transactionDate'] ?? $settlement['tranDate'] ?? null;
        }
    }

    /**
     * Convert BDT to poysha.
     */
    protected function bdtToPoysha(float $bdt): int
    {
        return (int) round($bdt * 100);
    }

    /**
     * Get reconciliation report for a specific period.
     */
    public function getReconciliationReport(string $startDate, string $endDate, int $tenantId, string $gateway = null): array
    {
        $query = PaymentReconciliation::where('tenant_id', $tenantId)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        if ($gateway) {
            $query->where('gateway', $gateway);
        }

        $reconciliations = $query->with('payment', 'payment.customer', 'payment.invoice')
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'status' => 'success',
            'period' => "{$startDate} to {$endDate}",
            'gateway' => $gateway ?? 'all',
            'total_records' => $reconciliations->count(),
            'matched' => $reconciliations->where('status', ReconciliationStatus::MATCHED->value)->count(),
            'pending' => $reconciliations->where('status', ReconciliationStatus::PENDING->value)->count(),
            'discrepancies' => $reconciliations->where('status', ReconciliationStatus::DISCREPANCY->value)->count(),
            'data' => $reconciliations,
        ];
    }

    /**
     * Get summary statistics for reconciliation.
     */
    public function getSummary(int $tenantId, string $period = 'daily'): array
    {
        $query = PaymentReconciliation::where('tenant_id', $tenantId);

        switch ($period) {
            case 'daily':
                $groupBy = 'DATE(created_at)';
                break;
            case 'weekly':
                $query->selectRaw('YEAR(created_at) as year, WEEK(created_at) as week');
                $groupBy = 'YEAR(created_at), WEEK(created_at)';
                break;
            case 'monthly':
                $groupBy = 'DATE_FORMAT(created_at, "%Y-%m")';
                break;
            default:
                $groupBy = 'DATE(created_at)';
        }

        $results = $query->selectRaw(
            "{$groupBy} as period, " .
            "SUM(CASE WHEN status = 'matched' THEN 1 ELSE 0 END) as matched_count, " .
            "SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count, " .
            "SUM(CASE WHEN status = 'discrepancy' THEN 1 ELSE 0 END) as discrepancy_count, " .
            "SUM(recorded_amount) as total_recorded, " .
            "SUM(settlement_amount) as total_settlement"
        )
        ->groupByRaw($groupBy)
        ->orderBy('period', 'desc')
        ->get();

        return [
            'status' => 'success',
            'period' => $period,
            'data' => $results,
        ];
    }
}
