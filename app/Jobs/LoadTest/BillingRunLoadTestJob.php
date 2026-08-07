<?php

namespace App\Jobs\LoadTest;

use App\Jobs\Billing\GenerateInvoices;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Billing Run Load Test Job
 *
 * Simulates a billing run at scale (100k+ subscriptions) to test performance.
 * This job creates test subscriptions and measures the time to generate invoices.
 *
 * Run with: php artisan queue:work --queue=loadtest
 * Or schedule for off-peak hours.
 */
class BillingRunLoadTestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * The number of subscriptions to create for the load test.
     */
    public int $targetCount;

    /**
     * Batch size for subscription creation.
     */
    public int $batchSize;

    /**
     * Create a new job instance.
     */
    public function __construct(int $targetCount = 100000, int $batchSize = 1000)
    {
        $this->targetCount = $targetCount;
        $this->batchSize = $batchSize;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::channel('loadtest')->info("Starting billing run load test for {$this->targetCount} subscriptions");

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Create test packages
        $package = $this->createTestPackage();

        // Create test customers and subscriptions in batches
        $createdCount = 0;
        $batchNumber = 0;

        while ($createdCount < $this->targetCount) {
            $batchSize = min($this->batchSize, $this->targetCount - $createdCount);
            $batchStart = microtime(true);

            $this->createBatch($package, $batchSize);
            $createdCount += $batchSize;
            $batchNumber++;

            $batchTime = microtime(true) - $batchStart;
            $subsPerSecond = round($batchSize / max($batchTime, 0.001), 2);

            Log::channel('loadtest')->info(
                "Batch {$batchNumber}: Created {$batchSize} subscriptions in {$batchTime}s ({$subsPerSecond} subs/sec)"
            );
        }

        // Memory check
        $endMemory = memory_get_usage(true);
        $memoryUsed = $endMemory - $startMemory;
        $memoryPerSub = round($memoryUsed / $createdCount, 2);

        Log::channel('loadtest')->info(
            "Created {$createdCount} subscriptions in total. Memory used: {$memoryUsed} bytes ({$memoryPerSub} bytes/sub)"
        );

        // Now run billing for all created subscriptions
        Log::channel('loadtest')->info('Starting invoice generation for all subscriptions...');

        $billingStart = microtime(true);

        // Dispatch GenerateInvoices job for all subscriptions
        // In a real load test, we'd use chunking or a more efficient approach
        $subscriptions = Subscription::query()
            ->where('billing_cycle_discount', 0) // Our test subscriptions
            ->with('customer', 'package')
            ->cursor();

        $dispatched = 0;
        foreach ($subscriptions as $subscription) {
            GenerateInvoices::dispatch($subscription);
            $dispatched++;

            if ($dispatched % 1000 === 0) {
                Log::channel('loadtest')->info("Dispatched {$dispatched} GenerateInvoices jobs");
            }
        }

        $billingTime = microtime(true) - $billingStart;
        $totalTime = microtime(true) - $startTime;

        Log::channel('loadtest')->info(
            "Billing run load test completed in {$totalTime}s. " .
            "Setup: " . ($billingStart - $startTime) . "s, Billing dispatch: {$billingTime}s"
        );

        // Log results
        $this->logResults($createdCount, $totalTime, $memoryUsed);
    }

    /**
     * Create a test package for load testing.
     */
    protected function createTestPackage(): Package
    {
        return Package::firstOrCreate(
            ['code' => 'LOADTEST-PKG'],
            [
                'uuid' => 'loadtest-pkg-' . md5('loadtest'),
                'name' => 'Load Test Package',
                'description' => 'Package for load testing',
                'download_speed' => 100,
                'upload_speed' => 50,
                'fup_threshold' => 1024 * 1024,
                'fup_throttled_download' => 10,
                'fup_throttled_upload' => 5,
                'price' => 100000, // 1000 BDT
                'billing_cycle' => 'monthly',
                'billing_type' => 'prepaid',
                'installation_fee' => 0,
                'security_deposit' => 0,
                'tax_rate' => 1500, // 15%
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 999,
                'features' => json_encode(['Load Test']),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Create a batch of test subscriptions.
     */
    protected function createBatch(Package $package, int $count): void
    {
        $subscriptions = [];
        $now = Carbon::now();

        for ($i = 0; $i < $count; $i++) {
            $startDate = $now->copy()->subDays(rand(1, 30));

            $subscriptions[] = [
                'tenant_id' => null,
                'uuid' => 'loadtest-sub-' . $i . '-' . md5($package->code),
                'customer_id' => null, // Will be set below
                'package_id' => $package->id,
                'reseller_id' => null,
                'created_by' => 1,
                'updated_by' => 1,
                'start_date' => $startDate,
                'end_date' => $startDate->copy()->addYear(),
                'next_billing_date' => $now->copy()->addDay(),
                'status' => 'active',
                'monthly_price' => $package->price,
                'billing_cycle_discount' => 0, // Marker for load test subscriptions
                'final_price' => $package->price,
                'is_prorated' => false,
                'proration_amount' => 0,
                'proration_notes' => null,
                'assigned_ip' => null,
                'assigned_mac' => null,
                'assigned_port' => null,
                'assigned_vlan' => null,
                'network_device_id' => null,
                'olt_id' => null,
                'onu_id' => null,
                'activated_at' => $now,
                'expired_at' => null,
                'cancelled_at' => null,
                'suspended_at' => null,
                'cancellation_reason' => null,
                'suspension_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // For efficiency, we'll create customers and subscriptions in bulk
        // But for now, we'll create them individually to avoid database deadlocks
        for ($i = 0; $i < $count; $i++) {
            $customer = Customer::firstOrCreate(
                ['email' => "loadtest-customer-{$i}@example.com"],
                [
                    'uuid' => 'loadtest-cust-' . $i,
                    'created_by' => 1,
                    'updated_by' => 1,
                    'first_name' => 'Load',
                    'last_name' => "Test-{$i}",
                    'phone' => '+' . str_pad(rand(1, 9999999999), 10, '0'),
                    'date_of_birth' => now()->subYears(rand(18, 60)),
                    'gender' => rand(0, 1) ? 'male' : 'female',
                    'nid_number' => str_pad(rand(1, 9999999999999999), 16, '0'),
                    'service_address' => "123 Load Test Street {$i}",
                    'service_latitude' => (float) (rand(-900000, 900000) / 10000),
                    'service_longitude' => (float) (rand(-1800000, 1800000) / 10000),
                    'billing_address' => "456 Load Test Ave {$i}",
                    'connection_type' => 'pppoe',
                    'status' => 'active',
                    'activated_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            Subscription::create([
                'tenant_id' => null,
                'uuid' => 'loadtest-sub-' . $i . '-' . md5($package->code),
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                'reseller_id' => null,
                'created_by' => 1,
                'updated_by' => 1,
                'start_date' => now()->subDays(rand(1, 30)),
                'end_date' => now()->addYear(),
                'next_billing_date' => now()->addDay(),
                'status' => 'active',
                'monthly_price' => $package->price,
                'billing_cycle_discount' => 0,
                'final_price' => $package->price,
                'is_prorated' => false,
                'proration_amount' => 0,
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Log the load test results.
     */
    protected function logResults(int $subscriptionCount, float $totalTime, int $memoryUsed): void
    {
        $subsPerSecond = round($subscriptionCount / max($totalTime, 0.001), 2);
        $memoryPerSub = round($memoryUsed / max($subscriptionCount, 1), 2);

        Log::channel('loadtest')->info("=== LOAD TEST RESULTS ===");
        Log::channel('loadtest')->info("Subscriptions created: {$subscriptionCount}");
        Log::channel('loadtest')->info("Total time: {$totalTime}s");
        Log::channel('loadtest')->info("Subscriptions per second: {$subsPerSecond}");
        Log::channel('loadtest')->info("Memory used: {$memoryUsed} bytes");
        Log::channel('loadtest')->info("Memory per subscription: {$memoryPerSub} bytes");
        Log::channel('loadtest')->info("=== END RESULTS ===");

        // Also log to a dedicated load test results file
        $results = [
            'timestamp' => now()->toIso8601String(),
            'test_type' => 'billing_run',
            'subscription_count' => $subscriptionCount,
            'total_time_seconds' => $totalTime,
            'subscriptions_per_second' => $subsPerSecond,
            'memory_used_bytes' => $memoryUsed,
            'memory_per_subscription_bytes' => $memoryPerSub,
            'target_scale' => '100k+',
        ];

        file_put_contents(
            storage_path('app/loadtest/billing_run_results_' . now()->format('Ymd_His') . '.json'),
            json_encode($results, JSON_PRETTY_PRINT)
        );
    }
}
