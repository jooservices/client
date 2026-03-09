<?php

declare(strict_types=1);

/**
 * Database configuration for tests and local use.
 * Connection is driven by env; defaults are for local MongoDB.
 */
return [
    'default' => env('DB_CONNECTION', 'mongodb'),

    'connections' => [
        'mongodb' => [
            'driver' => 'mongodb',
            'dsn' => env('MONGODB_URI', 'mongodb://127.0.0.1:27017'),
            'database' => env('MONGODB_DATABASE', 'jooclient_test'),
            'options' => [
                'appName' => env('MONGODB_APP_NAME', 'jooclient'),
            ],
        ],
    ],
];
