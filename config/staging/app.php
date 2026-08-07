<?php

return [
    'name' => env('APP_NAME', 'Fiberloop Staging'),
    'env' => env('APP_ENV', 'staging'),
    'debug' => env('APP_DEBUG', true),
    'url' => env('APP_URL', 'https://staging.fiberloop.com'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Dhaka'),
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],
    'maintenance' => [
        'driver' => 'file',
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
    'log' => env('APP_LOG', 'stack'),
    'log_level' => env('APP_LOG_LEVEL', 'debug'),
    'log_channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['staging', 'alerts', 'deployments'],
            'ignore_exceptions' => false,
        ],
        'staging' => [
            'driver' => 'daily',
            'path' => storage_path('logs/staging.log'),
            'level' => env('APP_LOG_LEVEL', 'debug'),
            'days' => 7,
        ],
        'alerts' => [
            'driver' => 'daily',
            'path' => storage_path('logs/alerts.log'),
            'level' => 'warning',
            'days' => 30,
        ],
        'deployments' => [
            'driver' => 'daily',
            'path' => storage_path('logs/deployments.log'),
            'level' => 'info',
            'days' => 30,
        ],
    ],
];
