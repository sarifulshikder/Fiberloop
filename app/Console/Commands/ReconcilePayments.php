<?php

namespace App\Console\Commands;

use App\Enums\PaymentMethod;
use App\Jobs\PaymentReconciliationJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to manually run payment reconciliation for gateways.
 * This can be run on-demand or scheduled via cron.
 */
class ReconcilePayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:reconcile 
        {gateway? : The gateway to reconcile (bkash, nagad, sslcommerz). If not specified, all gateways will be processed} 
        {--start= : Start date (YYYY-MM-DD). Default: yesterday} 
        {--end= : End date (YYYY-MM-DD). Default: today} 
        {--tenant= : Tenant ID. Default: 1} 
        {--queue : Dispatch jobs to queue instead of running synchronously}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile payments with gateway settlement reports';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $gateway = $this->argument('gateway');
        $startDate = $this->option('start') ?? now()->subDay()->format('Y-m-d');
        $endDate = $this->option('end') ?? now()->format('Y-m-d');
        $tenantId = (int) $this->option('tenant') ?? 1;
        $queue = (bool) $this->option('queue');

        $this->info("Payment Reconciliation");
        $this->info("=====================");
        $this->info("Tenant: {$tenantId}");
        $this->info("Period: {$startDate} to {$endDate}");
        $this->info("Queue: " . ($queue ? 'Yes' : 'No'));

        $gateways = [];

        if ($gateway) {
            // Validate gateway
            if (!in_array($gateway, [
                PaymentMethod::BKASH->value,
                PaymentMethod::NAGAD->value,
                PaymentMethod::SSLCOMMERZ->value
            ])) {
                $this->error("Invalid gateway: {$gateway}");
                return 1;
            }
            $gateways[] = $gateway;
        } else {
            // All gateways
            $gateways = [
                PaymentMethod::BKASH->value,
                PaymentMethod::NAGAD->value,
                PaymentMethod::SSLCOMMERZ->value,
            ];
        }

        foreach ($gateways as $gw) {
            $this->info("");
            $this->info("Reconciling {$gw}...");

            if ($queue) {
                // Dispatch to queue
                PaymentReconciliationJob::dispatch(
                    $gw,
                    $startDate,
                    $endDate,
                    $tenantId
                );
                $this->info("Dispatched reconciliation job for {$gw} to queue");
            } else {
                // Run synchronously
                try {
                    $job = new PaymentReconciliationJob($gw, $startDate, $endDate, $tenantId);
                    $job->handle(app(\App\Services\Payments\PaymentReconciliationService::class));
                    $this->info("Reconciliation completed for {$gw}");
                } catch (\Exception $e) {
                    $this->error("Reconciliation failed for {$gw}: " . $e->getMessage());
                    Log::error("Payment reconciliation command failed", [
                        'gateway' => $gw,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("");
        $this->info("Payment reconciliation complete!");

        return 0;
    }
}
