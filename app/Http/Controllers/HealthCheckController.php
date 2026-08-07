<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class HealthCheckController extends Controller
{
    /**
     * Perform a comprehensive health check of all system components.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $checks = [];

        // Database check
        try {
            DB::connection('pgsql')->select('SELECT 1');
            $checks['database'] = [
                'status' => 'healthy',
                'timestamp' => Carbon::now()->toISOString(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        } catch (\Exception $e) {
            $checks['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => Carbon::now()->toISOString(),
            ];
        }

        // Redis check
        try {
            $start = microtime(true);
            Redis::connection()->ping();
            $checks['redis'] = [
                'status' => 'healthy',
                'timestamp' => Carbon::now()->toISOString(),
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Exception $e) {
            $checks['redis'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => Carbon::now()->toISOString(),
            ];
        }

        // Cache check
        try {
            $start = microtime(true);
            Cache::put('health_check_test', 'healthy', 60);
            $value = Cache::get('health_check_test');
            Cache::forget('health_check_test');
            
            $checks['cache'] = [
                'status' => $value === 'healthy' ? 'healthy' : 'degraded',
                'timestamp' => Carbon::now()->toISOString(),
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Exception $e) {
            $checks['cache'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => Carbon::now()->toISOString(),
            ];
        }

        // Queue check
        try {
            $start = microtime(true);
            $queueStatus = app('queue')->size();
            
            $checks['queue'] = [
                'status' => 'healthy',
                'queue_size' => $queueStatus,
                'timestamp' => Carbon::now()->toISOString(),
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Exception $e) {
            $checks['queue'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => Carbon::now()->toISOString(),
            ];
        }

        // Application check
        try {
            $start = microtime(true);
            app()->booted();
            
            $checks['application'] = [
                'status' => 'healthy',
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'timestamp' => Carbon::now()->toISOString(),
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Exception $e) {
            $checks['application'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => Carbon::now()->toISOString(),
            ];
        }

        // RADIUS database check (if configured)
        try {
            if (config('database.connections.radius')) {
                $start = microtime(true);
                DB::connection('radius')->select('SELECT 1');
                
                $checks['radius_database'] = [
                    'status' => 'healthy',
                    'timestamp' => Carbon::now()->toISOString(),
                    'response_time_ms' => round((microtime(true) - $start) * 1000, 2),
                ];
            }
        } catch (\Exception $e) {
            $checks['radius_database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => Carbon::now()->toISOString(),
            ];
        }

        // Determine overall status
        $allHealthy = true;
        foreach ($checks as $check) {
            if ($check['status'] !== 'healthy') {
                $allHealthy = false;
                break;
            }
        }

        $overallStatus = $allHealthy ? 'healthy' : 'degraded';
        
        // Check if any are unhealthy
        foreach ($checks as $check) {
            if ($check['status'] === 'unhealthy') {
                $overallStatus = 'unhealthy';
                break;
            }
        }

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => Carbon::now()->toISOString(),
            'total_response_time_ms' => $totalTime,
            'components' => $checks,
            'metadata' => [
                'hostname' => gethostname(),
                'php_version' => PHP_VERSION,
            ],
        ], $overallStatus === 'healthy' ? 200 : 503);
    }

    /**
     * Simple ping endpoint for uptime monitoring.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'pong',
            'timestamp' => Carbon::now()->toISOString(),
        ]);
    }

    /**
     * Prometheus metrics endpoint.
     */
    public function metrics(): string
    {
        $metrics = [];

        // Application metrics
        $metrics[] = '# HELP fiberloop_up Uptime status (1 = up, 0 = down)';
        $metrics[] = '# TYPE fiberloop_up gauge';
        $metrics[] = 'fiberloop_up 1';

        // Database connection pool metrics
        try {
            $dbStatus = DB::connection('pgsql')->select('SELECT count(*) as total FROM pg_stat_activity');
            $activeConnections = $dbStatus[0]->total ?? 0;
            
            $metrics[] = '# HELP fiberloop_database_connections Total database connections';
            $metrics[] = '# TYPE fiberloop_database_connections gauge';
            $metrics[] = "fiberloop_database_connections $activeConnections";
        } catch (\Exception $e) {
            $metrics[] = "fiberloop_database_connections 0";
        }

        // Queue metrics
        try {
            $queueSize = app('queue')->size();
            $metrics[] = '# HELP fiberloop_queue_size Total jobs in queue';
            $metrics[] = '# TYPE fiberloop_queue_size gauge';
            $metrics[] = "fiberloop_queue_size $queueSize";
        } catch (\Exception $e) {
            $metrics[] = "fiberloop_queue_size 0";
        }

        // Cache hit rate metrics
        try {
            $cacheStats = Redis::connection()->info('stats');
            $keyspaceStats = Redis::connection()->info('keyspace');
            
            $hitRate = $cacheStats['keyspace_hits'] / max(1, ($cacheStats['keyspace_hits'] + $cacheStats['keyspace_misses'])) * 100;
            $usedMemory = $cacheStats['used_memory'] ?? 0;
            $totalKeys = $keyspaceStats['db0']['keys'] ?? 0;
            
            $metrics[] = '# HELP fiberloop_cache_hit_rate Cache hit rate percentage';
            $metrics[] = '# TYPE fiberloop_cache_hit_rate gauge';
            $metrics[] = "fiberloop_cache_hit_rate $hitRate";
            
            $metrics[] = '# HELP fiberloop_cache_memory_used Memory used by cache in bytes';
            $metrics[] = '# TYPE fiberloop_cache_memory_used gauge';
            $metrics[] = "fiberloop_cache_memory_used $usedMemory";
            
            $metrics[] = '# HELP fiberloop_cache_keys_total Total keys in cache';
            $metrics[] = '# TYPE fiberloop_cache_keys_total gauge';
            $metrics[] = "fiberloop_cache_keys_total $totalKeys";
        } catch (\Exception $e) {
            // No Redis cache metrics
        }

        // Memory usage
        if (function_exists('memory_get_usage')) {
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            
            $metrics[] = '# HELP fiberloop_memory_usage Current memory usage in bytes';
            $metrics[] = '# TYPE fiberloop_memory_usage gauge';
            $metrics[] = "fiberloop_memory_usage $memoryUsage";
            
            $metrics[] = '# HELP fiberloop_memory_peak Peak memory usage in bytes';
            $metrics[] = '# TYPE fiberloop_memory_peak gauge';
            $metrics[] = "fiberloop_memory_peak $memoryPeak";
        }

        // Custom business metrics (from cache or database)
        try {
            // Customer count
            $customerCount = DB::connection('pgsql')->table('customers')->count();
            $metrics[] = '# HELP fiberloop_customers_total Total customers';
            $metrics[] = '# TYPE fiberloop_customers_total gauge';
            $metrics[] = "fiberloop_customers_total $customerCount";
            
            // Active subscriptions
            $activeSubscriptions = DB::connection('pgsql')->table('subscriptions')
                ->where('status', 'active')->count();
            $metrics[] = '# HELP fiberloop_subscriptions_active Active subscriptions';
            $metrics[] = '# TYPE fiberloop_subscriptions_active gauge';
            $metrics[] = "fiberloop_subscriptions_active $activeSubscriptions";
            
            // Overdue invoices
            $overdueInvoices = DB::connection('pgsql')->table('invoices')
                ->where('due_date', '<', now())
                ->where('status', 'unpaid')->count();
            $metrics[] = '# HELP fiberloop_invoices_overdue Overdue invoices';
            $metrics[] = '# TYPE fiberloop_invoices_overdue gauge';
            $metrics[] = "fiberloop_invoices_overdue $overdueInvoices";
            
            // Today's payments
            $todayPayments = DB::connection('pgsql')->table('payments')
                ->whereDate('created_at', today())->count();
            $metrics[] = '# HELP fiberloop_payments_today Payments today';
            $metrics[] = '# TYPE fiberloop_payments_today gauge';
            $metrics[] = "fiberloop_payments_today $todayPayments";
        } catch (\Exception $e) {
            // Database not available for business metrics
        }

        return implode("\n", $metrics);
    }
}
