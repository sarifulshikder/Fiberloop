<?php

namespace App\Console\Commands;

use App\Jobs\AutoSuspend;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Artisan command to suspend subscriptions with overdue invoices.
 */
class AutoSuspendSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'billing:suspend-overdue 
        {--test : Run synchronously for testing} 
        {--force : Force run even if recently ran}';

    /**
     * The console command description.
     */
    protected $description = 'Automatically suspend subscriptions with overdue invoices past grace period';

    public function handle(): int
    {
        $this->info('Starting auto-suspend process...');
        
        if ($this->option('test')) {
            // Run synchronously for testing
            $job = new AutoSuspend();
            $job->handle();
        } else {
            // Dispatch as queued job
            AutoSuspend::dispatch();
            $this->info('Auto-suspend job dispatched to queue.');
        }
        
        Log::info('Auto-suspend command executed', [
            'mode' => $this->option('test') ? 'synchronous' : 'queued',
        ]);
        
        $this->info('Auto-suspend process completed.');
        return 0;
    }
}
