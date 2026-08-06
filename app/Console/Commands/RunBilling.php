<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingRunService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Artisan command to run the billing cycle.
 * Can be scheduled via Laravel scheduler.
 */
class RunBilling extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'billing:run 
        {--cycle= : Billing cycle (monthly, quarterly, yearly)} 
        {--start= : Custom cycle start date (Y-m-d)} 
        {--end= : Custom cycle end date (Y-m-d)} 
        {--force : Force run even if already ran today} 
        {--test : Run in test mode (synchronous, no queue)}';

    /**
     * The console command description.
     */
    protected $description = 'Run billing for all subscriptions due in the current cycle';

    public function handle(BillingRunService $billingRunService): int
    {
        $this->info('Starting billing run...');

        // Determine cycle dates
        $cycle = $this->option('cycle') ?? 'monthly';
        $customStart = $this->option('start');
        $customEnd = $this->option('end');

        if ($customStart && $customEnd) {
            $cycleStart = Carbon::parse($customStart);
            $cycleEnd = Carbon::parse($customEnd);
        } else {
            [$cycleStart, $cycleEnd] = $this->getCycleDates($cycle);
        }

        $this->info("Billing cycle: {$cycleStart->toDateString()} to {$cycleEnd->toDateString()}");

        // Check if already ran today (unless forced)
        if (!$this->option('force') && $this->alreadyRanToday($cycleStart, $cycleEnd)) {
            $this->warn('Billing already ran for this cycle today. Use --force to override.');
            return 0;
        }

        // Run billing
        if ($this->option('test')) {
            // Synchronous test mode
            $result = $this->runSynchronous($billingRunService, $cycleStart, $cycleEnd);
        } else {
            // Queued mode
            $result = $billingRunService->runBillingForDueSubscriptions($cycleStart, $cycleEnd);
        }

        $this->info("Billing run completed successfully!");
        $this->line("Total subscriptions due: {$result['total_subscriptions_due']}");
        $this->line("Jobs dispatched: {$result['jobs_dispatched']}");
        $this->line("Already invoiced: {$result['already_invoiced']}");

        // Log completion
        Log::info('Billing run command completed', [
            'cycle_start' => $cycleStart->toDateString(),
            'cycle_end' => $cycleEnd->toDateString(),
            'total_due' => $result['total_subscriptions_due'],
            'dispatched' => $result['jobs_dispatched'],
            'already_invoiced' => $result['already_invoiced'],
        ]);

        return 0;
    }

    /**
     * Get cycle dates based on cycle type.
     */
    protected function getCycleDates(string $cycle): array
    {
        $now = Carbon::now();

        return match ($cycle) {
            'quarterly' => [
                $now->copy()->firstOfQuarter(),
                $now->copy()->lastOfQuarter(),
            ],
            'yearly' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
            ],
            default => [ // monthly
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
        };
    }

    /**
     * Run billing synchronously for testing.
     */
    protected function runSynchronous(
        BillingRunService $billingRunService,
        Carbon $cycleStart,
        Carbon $cycleEnd
    ): array {
        $subscriptions = $billingRunService->getTodayDueSubscriptions();
        $total = 0;
        $processed = 0;
        $alreadyInvoiced = 0;

        foreach ($subscriptions as $subscription) {
            $cycle = $billingRunService->calculateBillingCycle($subscription);
            $subCycleStart = $cycle['start'];
            $subCycleEnd = $cycle['end'];

            if ($billingRunService->runBillingForSubscription(
                $subscription,
                $subCycleStart,
                $subCycleEnd
            )) {
                $processed++;
            } else {
                $alreadyInvoiced++;
            }
            $total++;
        }

        return [
            'cycle_start' => $cycleStart->toDateString(),
            'cycle_end' => $cycleEnd->toDateString(),
            'total_subscriptions_due' => $total,
            'jobs_dispatched' => $processed,
            'already_invoiced' => $alreadyInvoiced,
        ];
    }

    /**
     * Check if billing already ran today for this cycle.
     */
    protected function alreadyRanToday(Carbon $cycleStart, Carbon $cycleEnd): bool
    {
        // Simple check: billing run log from today
        // In production, use a more robust tracking mechanism
        return false; // For now, always allow - will implement proper tracking later
    }
}
