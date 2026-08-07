<?php

return [
    'octane' => [
        'server' => env('OCTANE_SERVER', 'frankenphp'),
        'host' => env('OCTANE_HOST', '0.0.0.0'),
        'port' => env('OCTANE_PORT', 8000),
        'workers' => env('OCTANE_WORKERS', 8),
        'max_requests' => env('OCTANE_MAX_REQUESTS', 200),
        'memory_limit' => env('OCTANE_MEMORY_LIMIT', '256M'),
    ],

    'horizon' => [
        'domain' => env('HORIZON_DOMAIN', 'staging.fiberloop.com'),
        'path' => env('HORIZON_PATH', '/horizon'),
        'use' => 'redis',
        'redis' => [
            'connection' => 'default',
        ],
        'fast' => [
            '超时' => 60,
        ],
    ],

    'reverb' => [
        'host' => env('REVERB_HOST', '0.0.0.0'),
        'port' => env('REVERB_PORT', 8080),
        'driver' => env('REVERB_DRIVER', 'redis'),
        'servers' => [
            [
                'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
                'port' => env('REVERB_SERVER_PORT', 8080),
                'host_verify' => false,
                'subscribers' => [
                    'app' => [
                        'host' => env('APP_URL', 'http://nginx:80'),
                        'app_id' => env('REVERB_APP_ID', 'staging'),
                        'app_key' => env('REVERB_APP_KEY'),
                        'app_secret' => env('REVERB_APP_SECRET'),
                    ],
                ],
            ],
        ],
    ],

    'billing' => [
        'cron_expression' => env('BILLING_CRON', '0 2 * * *'),
        'batch_size' => env('BILLING_BATCH_SIZE', 1000),
        'timeout' => env('BILLING_TIMEOUT', 3600),
    ],

    'freeradius' => [
        'host' => env('FREERADIUS_HOST', 'freeradius-staging'),
        'port' => env('FREERADIUS_PORT', 1812),
        'secret' => env('FREERADIUS_SECRET', ''),
        'timeout' => env('FREERADIUS_TIMEOUT', 5),
    ],

    'monitoring' => [
        'prometheus_enabled' => env('PROMETHEUS_ENABLED', true),
        'health_check_interval' => env('HEALTH_CHECK_INTERVAL', 60),
        'uptime_checks' => [
            'https://staging.fiberloop.com/health',
            'https://staging.fiberloop.com/admin',
        ],
    ],

    'alerting' => [
        'slack_webhook' => env('SLACK_WEBHOOK_STAGING'),
        'pagerduty_routing_key' => env('PAGERDUTY_ROUTING_KEY_STAGING'),
        'sms_provider' => env('SMS_PROVIDER_STAGING', 'log'),
    ],

    'backup' => [
        'enabled' => env('BACKUP_ENABLED', true),
        'schedule' => env('BACKUP_SCHEDULE', '0 3 * * *'),
        'retention_days' => env('BACKUP_RETENTION_DAYS', 14),
        'storage_disk' => env('BACKUP_STORAGE_DISK', 's3'),
        's3_bucket' => env('BACKUP_S3_BUCKET', 'fiberloop-backups-staging'),
    ],

    'ai_service' => [
        'url' => env('AI_SERVICE_URL', 'http://ai:8001'),
        'timeout' => env('AI_SERVICE_TIMEOUT', 30),
        'retries' => env('AI_SERVICE_RETRIES', 3),
    ],
];
