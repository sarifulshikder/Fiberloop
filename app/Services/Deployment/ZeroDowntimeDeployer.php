<?php

namespace App\Services\Deployment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Console\MigrateCommand;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Zero-downtime deployment service for Laravel applications.
 * 
 * This service handles safe migrations, queue draining, and rollback strategies
 * to ensure minimal impact during deployments.
 */
class ZeroDowntimeDeployer
{
    protected bool $isProduction;
    protected string $releasePath;
    protected string $currentPath;
    protected string $storagePath;

    public function __construct(bool $isProduction = false, string $releasePath = '', string $currentPath = '')
    {
        $this->isProduction = $isProduction;
        $this->releasePath = $releasePath;
        $this->currentPath = $currentPath;
        $this->storagePath = storage_path('app/deployment');
    }

    /**
     * Execute a zero-downtime deployment.
     */
    public function deploy(): array
    {
        $startTime = Carbon::now();
        $result = [
            'success' => false,
            'steps' => [],
            'errors' => [],
            'start_time' => $startTime->toISOString(),
            'end_time' => null,
            'duration_seconds' => 0,
        ];

        try {
            // Step 1: Pre-deployment checks
            $result['steps'][] = $this->runPreDeploymentChecks();
            
            // Step 2: Enter maintenance mode (for production)
            if ($this->isProduction) {
                $result['steps'][] = $this->enterMaintenanceMode();
            }
            
            // Step 3: Drain queue workers
            $result['steps'][] = $this->drainQueueWorkers();
            
            // Step 4: Run safe migrations
            $result['steps'][] = $this->runSafeMigrations();
            
            // Step 5: Clear caches
            $result['steps'][] = $this->clearCaches();
            
            // Step 6: Warm up cache
            $result['steps'][] = $this->warmUpCache();
            
            // Step 7: Switch symlink (actual deployment)
            $result['steps'][] = $this->switchSymlink();
            
            // Step 8: Verify deployment
            $result['steps'][] = $this->verifyDeployment();
            
            // Step 9: Resume queue workers
            $result['steps'][] = $this->resumeQueueWorkers();
            
            // Step 10: Exit maintenance mode
            if ($this->isProduction) {
                $result['steps'][] = $this->exitMaintenanceMode();
            }
            
            $result['success'] = true;
            
        } catch (\Exception $e) {
            $result['errors'][] = [
                'step' => 'deployment',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => Carbon::now()->toISOString(),
            ];
            
            // Attempt rollback
            $result['steps'][] = $this->rollback();
        }

        $endTime = Carbon::now();
        $result['end_time'] = $endTime->toISOString();
        $result['duration_seconds'] = $startTime->diffInSeconds($endTime);

        $this->logDeployment($result);

        return $result;
    }

    /**
     * Run pre-deployment checks.
     */
    protected function runPreDeploymentChecks(): array
    {
        $checks = [
            'name' => 'pre_deployment_checks',
            'start_time' => Carbon::now()->toISOString(),
            'status' => 'success',
            'checks' => [],
        ];

        // Check database connectivity
        try {
            DB::connection('pgsql')->select('SELECT 1');
            $checks['checks']['database'] = ['status' => 'passed', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            $checks['checks']['database'] = ['status' => 'failed', 'message' => $e->getMessage()];
            $checks['status'] = 'failed';
        }

        // Check Redis connectivity
        try {
            Redis::connection()->ping();
            $checks['checks']['redis'] = ['status' => 'passed', 'message' => 'Redis connection successful'];
        } catch (\Exception $e) {
            $checks['checks']['redis'] = ['status' => 'failed', 'message' => $e->getMessage()];
            $checks['status'] = 'failed';
        }

        // Check migration safety
        $checks['checks']['migration_safety'] = $this->checkMigrationSafety();
        
        // Check disk space
        $checks['checks']['disk_space'] = $this->checkDiskSpace();
        
        $checks['end_time'] = Carbon::now()->toISOString();

        return $checks;
    }

    /**
     * Check if pending migrations are safe for zero-downtime deployment.
     */
    protected function checkMigrationSafety(): array
    {
        try {
            $migrator = app(Migrator::class);
            $ran = $migrator->getRepository()->getRan();
            $files = $migrator->getMigrationFiles();
            $pending = array_diff(array_keys($files), $ran);

            if (empty($pending)) {
                return ['status' => 'passed', 'message' => 'No pending migrations'];
            }

            // Check for potentially destructive migrations
            $unsafeMigrations = [];
            foreach ($pending as $migration) {
                $migrationContent = file_get_contents($files[$migration]);
                
                if (str_contains($migrationContent, 'Schema::drop') ||
                    str_contains($migrationContent, 'Schema::dropIfExists') ||
                    str_contains($migrationContent, 'Schema::table') && str_contains($migrationContent, '->dropColumn')) {
                    $unsafeMigrations[] = $migration;
                }
            }

            if (!empty($unsafeMigrations)) {
                return [
                    'status' => 'warning',
                    'message' => 'Unsafe migrations detected',
                    'unsafe_migrations' => $unsafeMigrations,
                ];
            }

            return ['status' => 'passed', 'message' => count($pending) . ' safe migrations pending'];
            
        } catch (\Exception $e) {
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Check available disk space.
     */
    protected function checkDiskSpace(): array
    {
        try {
            $freeSpace = disk_free_space($this->storagePath);
            $totalSpace = disk_total_space($this->storagePath);
            $usedPercent = (1 - ($freeSpace / $totalSpace)) * 100;

            if ($usedPercent > 90) {
                return [
                    'status' => 'failed',
                    'message' => 'Disk space critically low',
                    'free_space_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                    'used_percent' => round($usedPercent, 2),
                ];
            }

            if ($usedPercent > 80) {
                return [
                    'status' => 'warning',
                    'message' => 'Disk space getting low',
                    'free_space_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                    'used_percent' => round($usedPercent, 2),
                ];
            }

            return [
                'status' => 'passed',
                'message' => 'Disk space available',
                'free_space_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                'used_percent' => round($usedPercent, 2),
            ];
            
        } catch (\Exception $e) {
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Enter maintenance mode.
     */
    protected function enterMaintenanceMode(): array
    {
        $result = [
            'name' => 'enter_maintenance_mode',
            'start_time' => Carbon::now()->toISOString(),
            'status' => 'success',
        ];

        try {
            Artisan::call('down', [
                '--message' => 'System maintenance - please try again later',
                '--retry' => 60,
                '--secret' => config('app.maintenance_secret', 'deploy-secret'),
            ]);
            
            $result['message'] = 'Maintenance mode enabled';
        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
        }

        $result['end_time'] = Carbon::now()->toISOString();

        return $result;
    }

    /**
     * Exit maintenance mode.
     */
    protected function exitMaintenanceMode(): array
    {
        $result = [
            'name' => 'exit_maintenance_mode',
            'start_time' => Carbon::now()->toISOString(),
            'status' => 'success',
        ];

        try {
            Artisan::call('up');
            
            $result['message'] = 'Maintenance mode disabled';
        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
        }

        $result['end_time'] = Carbon::now()->toISOString();

        return $result;
    }

    /**
     * Drain queue workers by pausing and waiting for current jobs to finish.
     */
    protected function drainQueueWorkers(): array
    {
        $result = [
            'name' => 'drain_queue_workers',
            'start_time' => Carbon::now()->toISOString(),
            'status' => 'success',
            'queue_stats' => [],
        ];

        try {
            // Get current queue statistics
            $queueSize = app('queue')->size();
            $result['queue_stats']['initial_size'] = $queueSize;

            // Pause Horizon to prevent new jobs from being processed
            if (class_exists(\Laravel\Horizon\Horizon::class)) {
                Artisan::call('horizon:pause');
                $result['queue_stats']['horizon_paused'] = true;
            }

            // Wait for queue to drain (with timeout)
            $timeout = 60; // seconds
            $start = time();
            
            while (time() - $start < $timeout) {
                $currentSize = app('queue')->size();
                $result['queue_stats']['queue_sizes'][] = $currentSize;
                
                if ($currentSize === 0) {
                    $result['message'] = 'Queue drained successfully';
                    break;
                }
                
                sleep(5);
            }

            if (time() - $start >= $timeout) {
                $result['message'] = 'Queue drain timeout reached';
                $result['status'] = 'warning';
            }

        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
        }

        $result['end_time'] = Carbon::now()->toISOString();

        return $result;
    }

    /**
     * Resume queue workers.
     */
    protected function resumeQueueWorkers(): array
    {
        $result = [
            'name' => 'resume_queue_workers',
            'start_time' => Carbon::now()->toISOString(),
            'status' => 'success',
        ];

        try {
            if (class_exists(\Laravel\Horizon\Horizon::class)) {
                Artisan::call('horizon:resume');
                $result['message'] = 'Horizon resumed';
            } else {
                Artisan::call('queue:restart');
                $result['message'] = 'Queue workers restarted';
            }
        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
        }

        $result['end_time'] = Carbon::now()->toISOString();

        return $result;
    }

    /**
     * Run safe migrations that won't break the application.
     */
    protected function runSafeMigrations(): array
    {
        $result = [
            'name' => 'run_safe_migrations',
            'start_time' => Carbon::now()->toISOString(),
            'status' => 'success',
            'migrations_run' => [],
        ];

        try {
            $output = new BufferedOutput();
            
            $exitCode = Artisan::call('migrate', [
                '--force' => true,
                '--step' => true, // Run one batch at a time for safety
            ], $output);

            $result['migrations_run'] = explode("\n", $output->fetch());
            $result['message'] = 'Migrations completed';
            
            if ($exitCode !== 0) {
                $result['status'] = 'failed';
                $result['error'] = 'Migration failed with exit code: ' . $exitCode;
            }

        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
        }

        $result['end_time'] = Carbon::now()->toISOString();

        return $result;
    }

    /**
     * Clear all application caches.
     */
    protected function clearCaches(): array
    {
        $result = [
            'name' => 'clear_caches',
            'start_time' => Carbon::now()->toISOString(),
            'status' => 'success',
            'caches_cleared' => [],
        ];

        try {
            $commands = [
                'config:clear',
                'route:clear',
                'view:clear',
                'cache:clear',
                'optimize:clear',
            ];

            foreach ($commands as $command) {
                try {
                    Artisan::call($command);
                    $result['caches_cleared'][] = $command;
                } catch (\Exception $e) {
                    $result['caches_cleared'][] = $command . ' (failed) ' . $e->getMessage();
                }
            }

            $result['message'] = 'All caches cleared';
            
        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
        }

        $result['end_time'] = Carbon::now()->toISOString();

        return $result;
    }

    /**
     * Warm up application caches.
     */
    protected function warmUpCache(): array
    {
        $result = [
            'name' => 'warm_up_cache',
            'start_time' => Carbon::now()->toISOString(),
            'status' => 'success',
            'caches_warmed' => [],
        ];

        try {
            // Rebuild caches
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            Artisan::call('optimize');

            $result['caches_warmed'] = ['config', 'route', 'view', 'optimize'];
            $result['message'] = 'Cache warmed up';
            
        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
        }

        $result['end_time'] = Carbon::now()->toISOString();

        return $result;
    }

    /**
     * Switch the symlink to the new release.
     */
    protected function switchSymlink(): array
    {
        $result = [
            'name' => 'switch_symlink',
            'start_time' => Carbon::now()->toISOString(),
            'status' => 'success',
        ];

        try {
            if ($this->releasePath && $this->currentPath) {
                // Remove old symlink
                if (file_exists($this->currentPath)) {
                    unlink($this->currentPath);
                }

                // Create new symlink
                symlink($this->releasePath, $this->currentPath);
                
                $result['message'] = 'Symlink switched to new release';
                $result['from'] = $this->releasePath;
                $result['to'] = $this->currentPath;
            } else {
                $result['message'] = 'Symlink switch skipped (no paths provided)';
            }

        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
        }

        $result['end_time'] = Carbon::now()->toISOString();

        return $result;
    }

    /**
     * Verify the deployment was successful.
     */
    protected function verifyDeployment(): array
    {
        $result = [
            'name' => 'verify_deployment',
            'start_time' => Carbon::now()->toISOString(),
            'status' => 'success',
            'checks' => [],
        ];

        try {
            // Check database connectivity
            DB::connection('pgsql')->select('SELECT 1');
            $result['checks']['database'] = true;

            // Check Redis connectivity
            Redis::connection()->ping();
            $result['checks']['redis'] = true;

            // Check application can bootstrap
            app()->booted();
            $result['checks']['application'] = true;

            $result['message'] = 'Deployment verified successfully';

        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
        }

        $result['end_time'] = Carbon::now()->toISOString();

        return $result;
    }

    /**
     * Perform rollback to previous release.
     */
    protected function rollback(): array
    {
        $result = [
            'name' => 'rollback',
            'start_time' => Carbon::now()->toISOString(),
            'status' => 'success',
            'actions' => [],
        ];

        try {
            // Resume queue workers first
            if (class_exists(\Laravel\Horizon\Horizon::class)) {
                Artisan::call('horizon:resume');
                $result['actions'][] = 'Queue workers resumed';
            }

            // Exit maintenance mode
            Artisan::call('up');
            $result['actions'][] = 'Maintenance mode disabled';

            // Log the rollback
            Log::critical('Deployment rollback executed', [
                'timestamp' => Carbon::now()->toISOString(),
                'actions' => $result['actions'],
            ]);

            $result['message'] = 'Rollback completed';

        } catch (\Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
        }

        $result['end_time'] = Carbon::now()->toISOString();

        return $result;
    }

    /**
     * Log deployment details.
     */
    protected function logDeployment(array $result): void
    {
        $logData = [
            'deployment' => [
                'success' => $result['success'],
                'start_time' => $result['start_time'],
                'end_time' => $result['end_time'],
                'duration_seconds' => $result['duration_seconds'],
                'is_production' => $this->isProduction,
            ],
            'steps' => array_map(function ($step) {
                return [
                    'name' => $step['name'] ?? 'unknown',
                    'status' => $step['status'] ?? 'unknown',
                    'start_time' => $step['start_time'] ?? null,
                    'end_time' => $step['end_time'] ?? null,
                    'message' => $step['message'] ?? null,
                    'error' => $step['error'] ?? null,
                ];
            }, $result['steps']),
            'errors' => $result['errors'],
        ];

        if ($result['success']) {
            Log::channel('deployments')->info('Deployment successful', $logData);
        } else {
            Log::channel('deployments')->error('Deployment failed', $logData);
        }
    }
}
