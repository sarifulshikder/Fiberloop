<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimitMiddleware
{
    /**
     * The rate limiter instance.
     */
    protected RateLimiter $limiter;

    /**
     * Create a new middleware instance.
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $maxAttempts = '60', string $decaySeconds = '60'): Response
    {
        // Skip rate limiting for certain routes
        $skipRoutes = [
            'api.v1.login',
            'api.v1.logout',
            'api.v1.refresh',
            'api.v1.me',
        ];

        if (in_array($request->route()?->getName(), $skipRoutes)) {
            return $next($request);
        }

        // Get the key for rate limiting
        $key = $this->resolveKey($request);

        // Apply different rate limits based on user type
        $limit = $this->getLimitForRequest($request, $maxAttempts, $decaySeconds);

        if ($this->limiter->tooManyAttempts($key, $limit->maxAttempts)) {
            return $this->buildResponse($key, $limit);
        }

        $this->limiter->hit($key, $limit->decaySeconds);

        $response = $next($request);

        return $this->addRateLimitHeaders($response, $key, $limit);
    }

    /**
     * Resolve the rate limiting key for the request.
     */
    protected function resolveKey(Request $request): string
    {
        $user = $request->user();

        if ($user) {
            // Use user ID for authenticated requests
            return 'api_rate_limit:' . $user->id . ':' . $request->ip();
        }

        // Use IP address for guest requests
        return 'api_rate_limit:guest:' . $request->ip();
    }

    /**
     * Get the rate limit for the request.
     */
    protected function getLimitForRequest(Request $request, string $maxAttempts, string $decaySeconds): Limit
    {
        $user = $request->user();

        // Higher limits for authenticated users
        if ($user) {
            // Check if user has a specific role
            $maxAttempts = match (true) {
                $user->hasRole('super_admin') => 300, // 5 requests per second
                $user->hasRole('admin') => 200,     // ~3.3 requests per second
                $user->hasRole(['noc_engineer', 'support_agent', 'billing_agent']) => 120, // ~2 requests per second
                $user->hasRole(['reseller', 'field_technician', 'customer']) => 60,  // 1 request per second
                default => 60,
            };

            $decaySeconds = 60; // 1 minute window
        } else {
            // Stricter limits for guests
            $maxAttempts = 30; // 0.5 requests per second
            $decaySeconds = 60;
        }

        return Limit::perMinute((int) $maxAttempts)->by($key)->response(function () {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
            ], 429);
        });
    }

    /**
     * Build the rate limit exceeded response.
     */
    protected function buildResponse(string $key, Limit $limit): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        return response()->json([
            'success' => false,
            'message' => 'Too many requests. Please try again later.',
            'error_code' => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => $retryAfter,
        ], 429, [
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $limit->maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /**
     * Add rate limit headers to the response.
     */
    protected function addRateLimitHeaders(Response $response, string $key, Limit $limit): Response
    {
        $attempts = $this->limiter->attempts($key);
        $remaining = max(0, $limit->maxAttempts - $attempts);

        return $response->headers->set('X-RateLimit-Limit', $limit->maxAttempts)
            ->set('X-RateLimit-Remaining', $remaining)
            ->set('X-RateLimit-Reset', time() + $limit->decaySeconds);
    }
}
