<?php

use FrankenPHP\Server\Worker\EventWorker;
use Spiral\RoadRunner\Worker;

return [
    'app' => [
        'workers' => [
            'app' => [
                'cmd' => 'php /var/www/html/artisan octane:start --host=0.0.0.0 --port=8000 --workers=4',
                'num_processes' => 4,
                'max_jobs' => 500,
            ],
        ],
    ],
];
