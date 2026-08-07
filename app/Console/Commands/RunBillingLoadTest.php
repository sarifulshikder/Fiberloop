<?php

namespace App\Console\Commands;

use App\Jobs\LoadTest\BillingRunLoadTestJob;
use Illuminate\Console\Command;

/**
 * Artisan command to run billing load test.
 * 
 * Usage:
 *   php artisan billing:load-test --subscriptions=10000
 *   php artisan billing:load-test --subscriptions=100000 --batch=5000
 */
class RunBillingLoadTest extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'billing:load-test
        {--subscriptions=1000 : Number of subscriptions to create (default: 1000 for quick test)}
        {--batch=1000 : Batch size for subscription creation}
        {--queue=loadtest : Queue to dispatch the job on}
        {--sync : Run the job synchronously instead of queuing it}';

    /**
     * The console command description.
     */
    protected $description = 'Run billing load test to simulate 100k+ subscription billing run';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $subscriptions = (int) $this->option('subscriptions');
        $batchSize = (int) $this->option('batch');
        $queue = $this->option('queue');
        $sync = $this->option('sync');

        $this->info("Starting billing load test for {$subscriptions} subscriptions...");
        $this->info("Batch size: {$batchSize}, Queue: {$queue}");

        if ($sync) {
            $this->info('Running synchronously...');
            
            $job = new BillingRunLoadTestJob($subscriptions, $batchSize);
            $job->handle();
            
            $this->info('Load test completed!');
            return 0;
        }

        // Dispatch the job
        $job = new BillingRunLoadTestJob($subscriptions, $batchSize);
        
        if ($queue) {
            $job->onQueue($queue);
        }

        dispatch($job);

        $this->info("Billing load test job dispatched to queue '{$queue}'");
        $this->info("Run 'php artisan queue:work --queue={$queue}' to process the job");

        return 0;
    }
}
