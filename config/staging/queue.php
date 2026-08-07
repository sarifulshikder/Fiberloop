<?php

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => env('QUEUE_NAME', 'default'),
            'retry_after' => 90,
            'block_for' => null,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('QUEUE_NAME', 'default'),
            'retry_after' => 90,
            'block_for' => null,
        ],

        'redis_high' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'high',
            'retry_after' => 30,
            'block_for' => null,
        ],

        'redis_low' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'low',
            'retry_after' => 300,
            'block_for' => null,
        ],
    ],

    'failed' => [
        'driver' => 'database-uuids',
        'database' => 'pgsql',
        'table' => 'failed_jobs',
    ],

    'batching' => [
        'database' => 'pgsql',
        'table' => 'job_batches',
    ],
];
