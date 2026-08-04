<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure rate limiting for authentication
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Use Laravel's rate limiter
        RateLimiter::for('login', function (Limit $limit) {
            return $limit->everyMinute(5)->by('email')->response(function () {
                return response()->json([
                    'message' => 'Too many login attempts',
                    'errors' => ['email' => ['Too many login attempts. Please try again in 1 minute.']],
                ], 429);
            });
        });

        RateLimiter::for('api', function (Limit $limit) {
            return $limit->everyMinute(60)->by('email')->response(function () {
                return response()->json([
                    'message' => 'Too many requests',
                ], 429);
            });
        });
    }
}
