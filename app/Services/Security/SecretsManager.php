<?php

namespace App\Services\Security;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Secrets Manager for secure credential storage and retrieval.
 * This service provides encryption for sensitive configuration values
 * and can integrate with external secrets managers like AWS Secrets Manager,
 * HashiCorp Vault, or Azure Key Vault.
 */
class SecretsManager
{
    /**
     * Cache duration in minutes for secrets.
     */
    protected int $cacheDuration = 60;

    /**
     * The encryption key.
     */
    protected string $encryptionKey;

    /**
     * Supported external secrets managers.
     */
    protected array $externalManagers = ['aws', 'vault', 'azure'];

    public function __construct()
    {
        $this->encryptionKey = config('app.key');
    }

    /**
     * Get a secret value with encryption fallback.
     * First tries environment variables, then encrypted storage,
     * then external secrets managers.
     */
    public function get(string $key, string $default = ''): string
    {
        // First check environment variables
        $value = $this->getFromEnvironment($key);
        if ($value !== null) {
            return $value;
        }

        // Check encrypted cache
        $value = $this->getFromCache($key);
        if ($value !== null) {
            return $value;
        }

        // Try external secrets managers
        $value = $this->getFromExternalManager($key);
        if ($value !== null) {
            $this->storeInCache($key, $value);
            return $value;
        }

        return $default;
    }

    /**
     * Store a secret value with encryption.
     */
    public function set(string $key, string $value): bool
    {
        // Encrypt and store in cache
        $encryptedValue = $this->encrypt($value);

        Cache::put(
            $this->getCacheKey($key),
            $encryptedValue,
            $this->cacheDuration * 60
        );

        return true;
    }

    /**
     * Check if a secret exists.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== '';
    }

    /**
     * Delete a secret from cache.
     */
    public function forget(string $key): bool
    {
        Cache::forget($this->getCacheKey($key));
        return true;
    }

    /**
     * Encrypt a value.
     */
    public function encrypt(string $value): string
    {
        $encrypter = $this->getEncrypter();
        return base64_encode($encrypter->encrypt($value));
    }

    /**
     * Decrypt a value.
     */
    public function decrypt(string $encryptedValue): string
    {
        $encrypter = $this->getEncrypter();
        return $encrypter->decrypt(base64_decode($encryptedValue));
    }

    /**
     * Generate a secure random secret.
     */
    public function generate(int $length = 32): string
    {
        return Str::random($length);
    }

    /**
     * Generate a secure API key.
     */
    public function generateApiKey(string $prefix = 'sk'): string
    {
        return $prefix . '_' . Str::random(32);
    }

    /**
     * Generate a secure webhook secret.
     */
    public function generateWebhookSecret(): string
    {
        return 'whsec_' . Str::random(40);
    }

    /**
     * Validate that a secret meets security requirements.
     */
    public function validate(string $secret, array $requirements = []): array
    {
        $errors = [];

        // Check minimum length
        $minLength = $requirements['min_length'] ?? 16;
        if (strlen($secret) < $minLength) {
            $errors[] = "Secret must be at least {$minLength} characters long";
        }

        // Check for common patterns
        if (preg_match('/(password|secret|123456|qwerty)/i', $secret)) {
            $errors[] = 'Secret contains common or easily guessable patterns';
        }

        // Check entropy
        if ($this->calculateEntropy($secret) < 3.0) {
            $errors[] = 'Secret has low entropy - use more random characters';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Calculate the entropy of a string.
     */
    protected function calculateEntropy(string $string): float
    {
        $charCounts = count_chars($string, 1);
        $length = strlen($string);
        $entropy = 0.0;

        foreach ($charCounts as $count) {
            if ($count > 0) {
                $probability = $count / $length;
                $entropy -= $probability * log($probability, 2);
            }
        }

        return $entropy;
    }

    /**
     * Get a secret from environment variables.
     */
    protected function getFromEnvironment(string $key): ?string
    {
        $envKey = strtoupper($key);
        $value = env($envKey);

        if ($value !== null && $value !== '') {
            return $value;
        }

        // Try with different prefixes
        $prefixes = ['APP_', 'FIBERLOOP_', ''];
        foreach ($prefixes as $prefix) {
            $fullKey = $prefix . strtoupper($key);
            $value = env($fullKey);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get a secret from encrypted cache.
     */
    protected function getFromCache(string $key): ?string
    {
        $cacheKey = $this->getCacheKey($key);
        $encryptedValue = Cache::get($cacheKey);

        if ($encryptedValue !== null) {
            try {
                return $this->decrypt($encryptedValue);
            } catch (\Exception $e) {
                Log::error('Failed to decrypt cached secret', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        }

        return null;
    }

    /**
     * Store a value in cache.
     */
    protected function storeInCache(string $key, string $value): void
    {
        $encryptedValue = $this->encrypt($value);
        Cache::put(
            $this->getCacheKey($key),
            $encryptedValue,
            $this->cacheDuration * 60
        );
    }

    /**
     * Get a secret from external secrets manager.
     */
    protected function getFromExternalManager(string $key): ?string
    {
        $manager = config('secrets.manager', 'none');

        if (!in_array($manager, $this->externalManagers, true)) {
            return null;
        }

        try {
            switch ($manager) {
                case 'aws':
                    return $this->getFromAwsSecretsManager($key);
                case 'vault':
                    return $this->getFromVault($key);
                case 'azure':
                    return $this->getFromAzureKeyVault($key);
                default:
                    return null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to retrieve secret from external manager', [
                'manager' => $manager,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get secret from AWS Secrets Manager.
     */
    protected function getFromAwsSecretsManager(string $key): ?string
    {
        // This would integrate with AWS SDK
        // For now, return null as this requires AWS configuration
        return null;
    }

    /**
     * Get secret from HashiCorp Vault.
     */
    protected function getFromVault(string $key): ?string
    {
        // This would integrate with Vault API
        // For now, return null as this requires Vault configuration
        return null;
    }

    /**
     * Get secret from Azure Key Vault.
     */
    protected function getFromAzureKeyVault(string $key): ?string
    {
        // This would integrate with Azure SDK
        // For now, return null as this requires Azure configuration
        return null;
    }

    /**
     * Get the cache key for a secret.
     */
    protected function getCacheKey(string $key): string
    {
        return 'secrets:' . md5($key);
    }

    /**
     * Get the encrypter instance.
     */
    protected function getEncrypter(): Encrypter
    {
        return new Encrypter(
            substr(hash('sha256', $this->encryptionKey), 0, 32),
            config('app.cipher', 'AES-256-CBC')
        );
    }
}
