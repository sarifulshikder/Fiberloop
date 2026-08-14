<?php

use App\Http\Controllers\HealthCheckController;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    Log::info('Testing logging from the root route');
    return view('welcome');
});

// Health check endpoints (no auth required for monitoring)
Route::get('/health', HealthCheckController::class)->name('health.check');
Route::get('/health/ping', [HealthCheckController::class, 'ping'])->name('health.pin');
Route::get('/metrics', [HealthCheckController::class, 'metrics'])->name('health.metrics');

// Customer Portal Routes - moved to Filament panel
Route::get('/filament/panels', function () {
    return \Filament::getPanels();
});
