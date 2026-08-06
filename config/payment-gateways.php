<?php

return [
    /**
     * Default payment gateway.
     */
    'default' => env('PAYMENT_GATEWAY_DEFAULT', 'sslcommerz'),

    /**
     * bKash configuration.
     * Sandbox credentials from https://developer.bka.sh/
     */
    'bkash' => [
        'enabled' => env('BKASH_ENABLED', false),
        'sandbox' => env('BKASH_SANDBOX', true),
        'app_key' => env('BKASH_APP_KEY', ''),
        'app_secret' => env('BKASH_APP_SECRET', ''),
        'username' => env('BKASH_USERNAME', ''),
        'password' => env('BKASH_PASSWORD', ''),
        'merchant_id' => env('BKASH_MERCHANT_ID', ''),

        'sandbox_base_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
        'production_base_url' => 'https://tokenized.pay.bka.sh/v1.2.0-beta',

        // Webhook configuration
        'webhook_secret' => env('BKASH_WEBHOOK_SECRET', ''),
        'webhook_url' => env('BKASH_WEBHOOK_URL', '/api/webhooks/bkash'),

        // Timeout in seconds
        'timeout' => 30,
    ],

    /**
     * Nagad configuration.
     * Sandbox credentials from https://developer.nagad.com.pk/
     */
    'nagad' => [
        'enabled' => env('NAGAD_ENABLED', false),
        'sandbox' => env('NAGAD_SANDBOX', true),
        'merchant_id' => env('NAGAD_MERCHANT_ID', ''),
        'merchant_number' => env('NAGAD_MERCHANT_NUMBER', ''),
        'api_key' => env('NAGAD_API_KEY', ''),
        'api_secret' => env('NAGAD_API_SECRET', ''),

        'sandbox_base_url' => 'https://sandbox.nagad.com.pk:443',
        'production_base_url' => 'https://api.nagad.com.pk:443',

        // Webhook configuration
        'webhook_secret' => env('NAGAD_WEBHOOK_SECRET', ''),
        'webhook_url' => env('NAGAD_WEBHOOK_URL', '/api/webhooks/nagad'),

        // Timeout in seconds
        'timeout' => 30,
    ],

    /**
     * SSLCommerz configuration.
     * Sandbox credentials from https://developer.sslcommerz.com/
     */
    'sslcommerz' => [
        'enabled' => env('SSLCOMMERZ_ENABLED', false),
        'sandbox' => env('SSLCOMMERZ_SANDBOX', true),
        'store_id' => env('SSLCOMMERZ_STORE_ID', ''),
        'store_password' => env('SSLCOMMERZ_STORE_PASSWORD', ''),

        'sandbox_base_url' => 'https://sandbox.sslcommerz.com',
        'production_base_url' => 'https://securepay.sslcommerz.com',

        // Webhook configuration
        'webhook_secret' => env('SSLCOMMERZ_WEBHOOK_SECRET', ''),
        'webhook_url' => env('SSLCOMMERZ_WEBHOOK_URL', '/api/webhooks/sslcommerz'),

        // IPN/Validation URL
        'validation_url' => env('SSLCOMMERZ_VALIDATION_URL', ''),

        // Success and fail URLs
        'success_url' => env('SSLCOMMERZ_SUCCESS_URL', '/payments/sslcommerz/success'),
        'fail_url' => env('SSLCOMMERZ_FAIL_URL', '/payments/sslcommerz/fail'),
        'cancel_url' => env('SSLCOMMERZ_CANCEL_URL', '/payments/sslcommerz/cancel'),

        // Timeout in seconds
        'timeout' => 30,
    ],

    /**
     * Idempotency key settings.
     */
    'idempotency' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour in seconds
    ],

    /**
     * Payment allocation settings for partial/split payments.
     */
    'allocation' => [
        // Allocation strategy: oldest_first, newest_first, largest_first, smallest_first
        'strategy' => env('PAYMENT_ALLOCATION_STRATEGY', 'oldest_first'),
    ],

    /**
     * Currency conversion (poysha to BDT).
     */
    'currency' => [
        'poysha_to_bdt' => 100, // 1 BDT = 100 poysha
    ],
];
