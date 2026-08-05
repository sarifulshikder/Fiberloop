<?php

namespace App\Jobs;

use App\Services\Billing\LateFeeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Job to process late fees for all overdue invoices.
 * Runs as a scheduled job.
 */
class ProcessLateFees implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $gracePeriodDays = 5;
    public int $lateFeePercentage = 10;

    public function __construct(int $gracePeriodDays = 5, int $lateFeePercentage = 10)
    {
        $this->gracePeriodDays = $gracePeriodDays;
        $this->lateFeePercentage = $lateFeePercentage;
    }

    public function handle(LateFeeService $lateFeeService): void
    {
        Log::info("Starting late fee processing job");
        
        $lateFeeService->setGracePeriod($this->gracePeriodDays);
        $lateFeeService->setLateFeePercentage($this->lateFeePercentage);
        
        $result = $lateFeeService->processAllOverdueInvoices();
        
        Log::info("Late fee processing completed", [
            'invoices_processed' => $result['total_invoices_processed'],
            'total_late_fees' => $result['total_late_fees_applied'],
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Late fee processing job failed", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}