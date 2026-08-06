<?php

use App\Jobs\PollDeviceMetricsJob;
use App\Jobs\Radius\EnforceFairUsagePolicy;
use App\Models\NetworkDevice;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FUP enforcement: run every 30 minutes to check data usage vs FUP thresholds
Schedule::job(new EnforceFairUsagePolicy())->everyThirtyMinutes()->name('radius:fup-enforcement');

// Network device polling: every 5 minutes, dispatch one job per active device.
// Decisions log: 5-minute interval chosen — fine for ISP NOC visibility without
// overwhelming devices. Store results time-series in device_metrics table (not
// a separate time-series store — at ≤1000 devices × 288 polls/day = 288k rows/day,
// PostgreSQL handles this easily; can migrate to TimescaleDB later if needed).
Schedule::call(function () {
    NetworkDevice::where('is_active', true)->each(function (NetworkDevice $device) {
        PollDeviceMetricsJob::dispatch($device);
    });
})->everyFiveMinutes()->name('network:poll-device-metrics');

// ONU optical signal polling: every 30 minutes
Schedule::call(function () {
    \App\Models\Onu::where('is_active', true)->each(function (\App\Models\Onu $onu) {
        \App\Jobs\PollOnuOpticalSignalJob::dispatch($onu);
    });
})->everyThirtyMinutes()->name('network:poll-onu-signals');

// Daily collection summary email to admins: every day at 8 AM
Schedule::command('reports:daily-collection-summary')
    ->dailyAt('08:00')
    ->name('reports:daily-collection-summary')
    ->withoutOverlapping();

// SLA breach check: every hour
Schedule::job(new \App\Jobs\CheckSlaBreaches())
    ->hourly()
    ->name('tickets:check-sla-breaches')
    ->withoutOverlapping();

// AI Retraining and analysis: weekly
Schedule::command('ai:run-analysis')
    ->weekly()
    ->name('ai:run-analysis')
    ->withoutOverlapping();
