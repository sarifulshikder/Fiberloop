<?php

namespace App\Jobs;

use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled job to run payment reconciliation for all gateways.
 * This job fetches settlement reports from each gateway and matches
 * them against recorded payments to identify discrepancies.
 */
class PaymentReconciliationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $gateway;
    protected string $startDate;
    protected string $endDate;
    protected int $tenantId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $gateway, string $startDate, string $endDate, int $tenantId)
    {
        $this->gateway = $gateway;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->tenantId = $tenantId;
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentReconciliationService $reconciliationService): void
    {
        Log::info("Starting payment reconciliation", [
            'gateway' => $this->gateway,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'tenant_id' => $this->tenantId,
        ]);

        try {
            $results = $reconciliationService->reconcile(
                $this->gateway,
                $this->startDate,
                $this->endDate,
                $this->tenantId
            );

            Log::info("Payment reconciliation completed", [
                'gateway' => $this->gateway,
                'total_settlements' => $results['total_settlements'],
                'total_recorded' => $results['total_recorded'],
                'matched' => $results['matched'],
                'discrepancies' => count($results['discrepancies']),
            ]);

            // If there are discrepancies, log them for follow-up
            if (!empty($results['discrepancies'])) {
                foreach ($results['discrepancies'] as $discrepancy) {
                    Log::warning("Payment reconciliation discrepancy found", [
                        'gateway' => $this->gateway,
                        'type' => $discrepancy['type'],
                        'payment_id' => $discrepancy['payment_id'] ?? null,
                        'gateway_reference' => $discrepancy['gateway_reference'] ?? null,
                        'amount' => $discrepancy['settlement_amount'] ?? $discrepancy['recorded_amount'] ?? null,
                        'message' => $discrepancy['message'],
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error("Payment reconciliation job failed", [
                'gateway' => $this->gateway,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware(): array
    {
        return [];
    }

    /**
     * Get the timeout for the job.
     */
    public function timeout(): int
    {
        return 300; // 5 minutes timeout for reconciliation
    }

    /**
     * Get the retry delay in seconds.
     */
    public function retryAfter(): int
    {
        return 300; // Retry after 5 minutes if failed
    }

    /**
     * The number of times the job may be attempted.
     */
    public function retries(): int
    {
        return 3; // Retry up to 3 times
    }
}
