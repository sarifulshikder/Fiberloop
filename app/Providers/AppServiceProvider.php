<?php

namespace App\Providers;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        \Illuminate\Support\Facades\RateLimiter::for('sms_sends', function ($job) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(50);
        });
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
