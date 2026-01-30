<?php

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use JOOservices\Client\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->build();

// Ensure the client supports async (it does by default via Guzzle adapter)
if (!method_exists($client, 'getAsync')) {
    die("This client adapter does not support async requests.\n");
}

echo "Starting async requests...\n";

// Initiate requests
$promise1 = $client->getAsync('/posts/1');
$promise2 = $client->getAsync('/posts/2');
$promise3 = $client->getAsync('/posts/3');

// Wait for them
$results = \GuzzleHttp\Promise\Utils::unwrap([
    'post1' => $promise1,
    'post2' => $promise2,
    'post3' => $promise3,
]);

foreach ($results as $key => $response) {
    $data = $response->json();
    echo "{$key}: {$data['title']}\n";
}
