<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to protect the database from accidental destructive operations.
 * This provides a list of dangerous commands and their safe alternatives.
 */
class ProtectDatabase extends Command
{
    /**
     * Dangerous commands that can destroy data.
     */
    protected array $dangerousCommands = [
        'migrate:fresh',
        'migrate:refresh',
        'db:wipe',
        'migrate:reset',
        'schema:drop',
        'db:seed --force',
    ];

    /**
     * Safe alternatives for dangerous commands.
     */
    protected array $safeAlternatives = [
        'migrate:fresh' => 'migrate',
        'migrate:refresh' => 'migrate',
        'db:wipe' => 'db:seed-safe',
        'migrate:reset' => 'migrate:status',
        'schema:drop' => 'schema:dump',
        'db:seed --force' => 'db:seed-safe',
    ];

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:protect
        {--list : List all dangerous commands and their safe alternatives}
        {--check : Check if any dangerous commands are about to be executed}';

    /**
     * The console command description.
     */
    protected $description = 'Protect database from destructive commands and show safe alternatives';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listDangerousCommands();
        }

        if ($this->option('check')) {
            return $this->checkForDangerousCommands();
        }

        $this->info('Database Protection Command');
        $this->info('==========================');
        $this->newLine();
        $this->info('This command helps prevent accidental data loss.');
        $this->newLine();
        $this->info('Usage:');
        $this->info('  php artisan db:protect --list    Show all dangerous commands and safe alternatives');
        $this->info('  php artisan db:protect --check    Check for dangerous commands');
        $this->newLine();

        $this->warn('⚠️  WARNING: The following commands can DELETE your data:');
        foreach ($this->dangerousCommands as $command) {
            $this->line('   - ' . $command);
        }
        $this->newLine();

        $this->info('✅ Use these SAFE commands instead:');
        $this->info('   - php artisan migrate              Run only new migrations (keeps data)');
        $this->info('   - php artisan db:seed-safe          Seed database without deleting data');
        $this->info('   - php artisan db:seed               Seed database (checks for existing data)');
        $this->newLine();

        return 0;
    }

    /**
     * List all dangerous commands and their safe alternatives.
     */
    protected function listDangerousCommands(): int
    {
        $this->info('Dangerous Commands and Safe Alternatives');
        $this->info('========================================');
        $this->newLine();

        $this->warn('DANGEROUS (Destroys Data):');
        foreach ($this->dangerousCommands as $command) {
            $this->line('  ❌ ' . str_pad($command, 25) . ' - DESTROYS YOUR DATA!');
        }
        $this->newLine();

        $this->info('SAFE (Preserves Data):');
        foreach ($this->safeAlternatives as $dangerous => $safe) {
            $this->line('  ✅ ' . str_pad($dangerous, 25) . ' → ' . $safe);
        }
        $this->line('  ✅ ' . str_pad('db:seed', 25) . ' → Checks for existing data first');
        $this->line('  ✅ ' . str_pad('db:seed-safe', 25) . ' → Safest option, never deletes');
        $this->newLine();

        return 0;
    }

    /**
     * Check for dangerous commands in the current process.
     */
    protected function checkForDangerousCommands(): int
    {
        $this->info('Checking for dangerous commands...');

        // Get the current command being executed
        global $argv;
        $commandString = implode(' ', $argv);

        foreach ($this->dangerousCommands as $dangerousCommand) {
            if (str_contains($commandString, $dangerousCommand)) {
                $this->error('❌ DANGER: Detected destructive command!');
                $this->error('Command: ' . $dangerousCommand);
                $this->error('This command will DELETE your data!');
                $this->newLine();

                if (isset($this->safeAlternatives[$dangerousCommand])) {
                    $this->info('Safe alternative: ' . $this->safeAlternatives[$dangerousCommand]);
                }

                $this->newLine();
                $this->error('ABORTED: Command not executed.');
                Log::warning('Dangerous database command blocked: ' . $dangerousCommand);

                return 1; // Exit with error code
            }
        }

        $this->info('✅ No dangerous commands detected.');
        return 0;
    }
}
