<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Service for handling idempotency keys to prevent duplicate payment processing.
 * This is critical for preventing double-charging on retry or duplicate webhook delivery.
 */
class IdempotencyService
{
    protected int $ttl;

    public function __construct()
    {
        $this->ttl = (int) config('payment-gateways.idempotency.ttl', 3600);
    }

    /**
     * Generate a unique idempotency key for a payment request.
     * Format: {gateway}_{customer_id}_{timestamp}_{random}
     */
    public function generateKey(string $gateway, int $customerId): string
    {
        return sprintf(
            '%s_%d_%s_%s',
            $gateway,
            $customerId,
            now()->format('YmdHis'),
            Str::random(8)
        );
    }

    /**
     * Check if an idempotency key has already been used.
     * Returns the cached response if found, null otherwise.
     */
    public function check(string $key): ?array
    {
        if (!config('payment-gateways.idempotency.enabled', true)) {
            return null;
        }

        $cached = Cache::get($this->cacheKey($key));
        
        return $cached === null ? null : $cached;
    }

    /**
     * Store a response against an idempotency key.
     * This prevents duplicate processing of the same request.
     */
    public function store(string $key, array $response): bool
    {
        if (!config('payment-gateways.idempotency.enabled', true)) {
            return false;
        }

        return Cache::put(
            $this->cacheKey($key),
            $response,
            $this->ttl
        );
    }

    /**
     * Remove an idempotency key from cache.
     */
    public function forget(string $key): bool
    {
        return Cache::forget($this->cacheKey($key));
    }

    /**
     * Get the cache key for an idempotency key.
     */
    protected function cacheKey(string $idempotencyKey): string
    {
        return 'payment_idempotency_' . $idempotencyKey;
    }

    /**
     * Execute a callback with idempotency protection.
     * If the key exists, returns the cached response.
     * Otherwise, executes the callback and caches the result.
     */
    public function execute(string $key, callable $callback): array
    {
        $cached = $this->check($key);
        
        if ($cached !== null) {
            return $cached;
        }

        $response = $callback();
        $this->store($key, $response);
        
        return $response;
    }
}
