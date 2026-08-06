<?php

use App\Jobs\Radius\EnforceFairUsagePolicy;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FUP enforcement: run every 30 minutes to check data usage vs FUP thresholds
Schedule::job(new EnforceFairUsagePolicy())->everyThirtyMinutes()->name('radius:fup-enforcement');
