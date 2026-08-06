<?php

namespace App\Jobs\Radius;

use App\Models\RadAcct;
use App\Models\RadiusCustomer;
use App\Models\RadReply;
use App\Models\Subscription;
use App\Services\Radius\RadiusCoaService;
use App\Services\Radius\RadiusProvisioningService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnforceFairUsagePolicy implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Execute the FUP enforcement job.
     */
    public function handle(RadiusProvisioningService $provisioningService, RadiusCoaService $coaService): void
    {
        Log::info("Starting scheduled FUP enforcement job...");

        // Fetch active subscriptions with FUP packages
        $subscriptions = Subscription::with(['customer', 'package'])
            ->where('status', 'active')
            ->whereHas('package', function ($query) {
                $query->whereNotNull('fup_threshold')
                    ->where('fup_threshold', '>', 0);
            })
            ->get();

        $processedCount = 0;
        $throttledCount = 0;
        $restoredCount = 0;

        foreach ($subscriptions as $subscription) {
            $customer = $subscription->customer;
            $package = $subscription->package;

            if (!$customer || !$package) {
                continue;
            }

            $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)->first();
            if (!$radiusCustomer || !$radiusCustomer->radius_username) {
                continue;
            }

            $username = $radiusCustomer->radius_username;
            $processedCount++;

            // Current billing cycle start
            $cycleStart = $subscription->start_date ?? $subscription->activated_at ?? Carbon::now()->startOfMonth();

            // Calculate total bytes (input + output) from radacct since cycle start
            $totalBytes = RadAcct::where('username', $username)
                ->where(function ($query) use ($cycleStart) {
                    $query->where('acctstarttime', '>=', $cycleStart)
                        ->orWhereNull('acctstarttime');
                })
                ->selectRaw('SUM(COALESCE(acctinputoctets, 0) + COALESCE(acctoutputoctets, 0)) as total_octets')
                ->value('total_octets') ?? 0;

            $thresholdBytes = $package->fup_threshold * 1024 * 1024 * 1024; // GB to bytes

            $currentRateLimit = RadReply::where('username', $username)
                ->where('attribute', 'Mikrotik-Rate-Limit')
                ->value('value');

            $throttledDownload = $package->fup_throttled_download ?? max(1, (int) ($package->download_speed * 0.2));
            $throttledUpload = $package->fup_throttled_upload ?? max(1, (int) ($package->upload_speed * 0.2));
            $throttledRateString = $provisioningService->formatRateLimit($throttledUpload, $throttledDownload);

            if ($totalBytes >= $thresholdBytes) {
                // Exceeded FUP threshold -> Throttle speed
                if ($currentRateLimit !== $throttledRateString) {
                    Log::info("FUP limit crossed for customer #{$customer->id} ({$username}). Throttling speed to {$throttledRateString}.", [
                        'usage_bytes' => $totalBytes,
                        'threshold_bytes' => $thresholdBytes,
                    ]);

                    $provisioningService->updateBandwidthProfile($customer, $throttledDownload, $throttledUpload, $throttledRateString);
                    $coaService->sendCoa($username, $throttledRateString);

                    $throttledCount++;
                }
            } else {
                // Under FUP threshold -> Restore package speed if currently throttled
                $normalRateString = $provisioningService->formatRateLimit($package->upload_speed, $package->download_speed);

                if ($currentRateLimit === $throttledRateString) {
                    Log::info("FUP cycle reset/under limit for customer #{$customer->id} ({$username}). Restoring speed to {$normalRateString}.");

                    $provisioningService->updateBandwidthProfile($customer, $package->download_speed, $package->upload_speed, $normalRateString);
                    $coaService->sendCoa($username, $normalRateString);

                    $restoredCount++;
                }
            }
        }

        Log::info("FUP enforcement completed. Processed: {$processedCount}, Throttled: {$throttledCount}, Restored: {$restoredCount}");
    }
}
