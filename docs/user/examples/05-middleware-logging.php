<?php

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use JOOservices\Client\Client\ClientBuilder;

// Create a temp log file
$logFile = sys_get_temp_dir() . '/jooclient_example.log';
if (file_exists($logFile)) {
    unlink($logFile);
}

echo "Logging to: $logFile\n";

$builder = ClientBuilder::create()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->withDefaultLogging('example-app', $logFile); // Helper to setup Monolog

$client = $builder->build();

echo "Making a request...\n";
$client->get('/posts/1');

echo "Log file content:\n";
echo file_get_contents($logFile);
