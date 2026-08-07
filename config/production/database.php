<?php

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'postgres-production'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'fiberloop'),
            'username' => env('DB_USERNAME', 'fiberloop'),
            'password' => env('DB_PASSWORD', 'production_password_change_me'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'public',
            'sslmode' => 'require',
            'sslrootcert' => env('DB_SSL_ROOT_CERT', null),
            'sslcert' => env('DB_SSL_CERT', null),
            'sslkey' => env('DB_SSL_KEY', null),
        ],

        'radius' => [
            'driver' => 'pgsql',
            'host' => env('RADIUS_DB_HOST', 'postgres-production'),
            'port' => env('RADIUS_DB_PORT', '5432'),
            'database' => env('RADIUS_DB_DATABASE', 'fiberloop_radius'),
            'username' => env('RADIUS_DB_USERNAME', 'fiberloop_radius'),
            'password' => env('RADIUS_DB_PASSWORD', 'production_radius_password_change_me'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'schema' => 'radius',
            'sslmode' => 'require',
        ],
    ],

    'migrations' => 'migrations',

    'redis' => [
        'client' => 'predis',

        'default' => [
            'host' => env('REDIS_HOST', 'redis-production'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],

        'cache' => [
            'host' => env('REDIS_HOST', 'redis-production'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],

        'queue' => [
            'host' => env('REDIS_HOST', 'redis-production'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_QUEUE_DB', 2),
        ],
    ],
];
