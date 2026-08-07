<?php

use App\Http\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Health check endpoints (no auth required for monitoring)
Route::get('/health', HealthCheckController::class)->name('health.check');
Route::get('/health/ping', [HealthCheckController::class, 'ping'])->name('health.ping');
Route::get('/metrics', [HealthCheckController::class, 'metrics'])->name('health.metrics');

// Customer Portal Routes
require __DIR__.'/customer.php';
