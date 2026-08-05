<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

/**
 * Base class for all payment gateway services.
 * Provides common functionality and enforces consistent behavior.
 */
abstract class BaseGatewayService
{
    protected string $gatewayName;
    protected IdempotencyService $idempotencyService;
    
    /**
     * Amount multiplier for currency conversion (poysha to BDT).
     * Bangladesh: 1 BDT = 100 poysha, so divide by 100.
     */
    protected float $amountMultiplier = 0.01;

    public function __construct(IdempotencyService $idempotencyService)
    {
        $this->idempotencyService = $idempotencyService;
    }

    /**
     * Get the gateway configuration.
     */
    protected function config(): array
    {
        return config("payment-gateways.{$this->gatewayName}", []);
    }

    /**
     * Check if the gateway is enabled.
     */
    protected function isEnabled(): bool
    {
        return (bool) ($this->config()['enabled'] ?? false);
    }

    /**
     * Check if the gateway is in sandbox mode.
     */
    protected function isSandbox(): bool
    {
        return (bool) ($this->config()['sandbox'] ?? true);
    }

    /**
     * Get the base URL for API calls.
     */
    protected function getBaseUrl(): string
    {
        $config = $this->config();
        
        if ($this->isSandbox()) {
            return $config['sandbox_base_url'] ?? '';
        }
        
        return $config['production_base_url'] ?? '';
    }

    /**
     * Get the webhook secret for signature verification.
     */
    protected function getWebhookSecret(): string
    {
        return $this->config()['webhook_secret'] ?? '';
    }

    /**
     * Get timeout in seconds.
     */
    protected function getTimeout(): int
    {
        return (int) ($this->config()['timeout'] ?? 30);
    }

    /**
     * Convert poysha (BDT x 100) to BDT for gateway API calls.
     */
    protected function poyshaToBdt(int $poysha): float
    {
        return $poysha * $this->amountMultiplier;
    }

    /**
     * Convert BDT to poysha for internal storage.
     */
    protected function bdtToPoysha(float $bdt): int
    {
        return (int) round($bdt / $this->amountMultiplier);
    }

    /**
     * Make a POST request to the gateway API.
     */
    protected function post(string $endpoint, array $data, array $headers = []): array
    {
        $url = rtrim($this->getBaseUrl(), '/') . '/' . ltrim($endpoint, '/');
        
        $defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        $response = Http::withHeaders($allHeaders)
            ->withOptions(['verify' => false]) // For sandbox environments
            ->timeout($this->getTimeout())
            ->post($url, $data);
        
        if ($response->successful()) {
            return $response->json() ?? [];
        }
        
        $this->logError($endpoint, $data, $response->status(), $response->body());
        
        throw new Exception(
            "Gateway API request failed: {$response->status()} - {$response->body()}"
        );
    }

    /**
     * Make a GET request to the gateway API.
     */
    protected function get(string $endpoint, array $query = [], array $headers = []): array
    {
        $url = rtrim($this->getBaseUrl(), '/') . '/' . ltrim($endpoint, '/');
        
        $defaultHeaders = [
            'Accept' => 'application/json',
        ];
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        $response = Http::withHeaders($allHeaders)
            ->withOptions(['verify' => false])
            ->timeout($this->getTimeout())
            ->get($url, $query);
        
        if ($response->successful()) {
            return $response->json() ?? [];
        }
        
        $this->logError($endpoint, $query, $response->status(), $response->body());
        
        throw new Exception(
            "Gateway API request failed: {$response->status()} - {$response->body()}"
        );
    }

    /**
     * Log an API error.
     */
    protected function logError(string $endpoint, array $data, int $status, string $body): void
    {
        Log::error("Payment gateway API error", [
            'gateway' => $this->gatewayName,
            'endpoint' => $endpoint,
            'status' => $status,
            'request_data' => $data,
            'response_body' => $body,
        ]);
    }

    /**
     * Generate a unique transaction reference.
     */
    protected function generateTransactionReference(): string
    {
        return Str::upper(Str::random(12));
    }

    /**
     * Validate that required configuration is present.
     */
    protected function validateConfiguration(array $requiredKeys): void
    {
        $config = $this->config();
        
        foreach ($requiredKeys as $key) {
            if (empty($config[$key])) {
                throw new Exception(
                    "Missing required configuration for {$this->gatewayName}: {$key}"
                );
            }
        }
    }
}
