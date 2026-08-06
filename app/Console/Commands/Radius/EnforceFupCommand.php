<?php

namespace App\Console\Commands\Radius;

use App\Jobs\Radius\EnforceFairUsagePolicy;
use Illuminate\Console\Command;

class EnforceFupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'radius:enforce-fup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect radacct data consumption against package FUP limits and adjust bandwidth profiles';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Dispatching FUP enforcement job...');
        dispatch_sync(new EnforceFairUsagePolicy());
        $this->info('FUP enforcement completed successfully.');

        return Command::SUCCESS;
    }
}
