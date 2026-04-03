<?php

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use JOOservices\Client\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->withTimeout(1) // Very short timeout to trigger error easily
    ->build();

echo "Testing timeout (connecting to non-routable IP)...\n";

try {
    // 10.255.255.1 is typically non-routable/dropped
    $client->get('http://10.255.255.1/test');
} catch (ConnectException $e) {
    echo "Caught ConnectException (Timeout/Network): " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "Caught other exception: " . get_class($e) . "\n";
}

echo "\nTesting 404...\n";
$client2 = ClientBuilder::create()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->build();

try {
    $client2->get('/posts/999999');
} catch (ClientException $e) {
    echo "Caught ClientException (4xx): " . $e->getResponse()->getStatusCode() . "\n";
}
