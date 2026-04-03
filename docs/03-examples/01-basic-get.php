<?php

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use GuzzleHttp\Exception\ClientException;
use JOOservices\Client\Client\ClientBuilder;

// 1. Create the builder
$builder = ClientBuilder::create()
    ->withBaseUri('https://jsonplaceholder.typicode.com') // Using a public test API
    ->withTimeout(5);

// 2. Build the client
$client = $builder->build();

echo "Fetching post #1...\n";

try {
    // 3. Make the request
    $response = $client->get('/posts/1');

    // 4. Handle response
    if ($response->status() >= 200 && $response->status() < 300) {
        $data = $response->json();
        echo "Title: " . $data['title'] . "\n";
        echo "Body: " . $data['body'] . "\n";
    } else {
        echo "Error: Status " . $response->status() . "\n";
    }

} catch (ClientException $e) {
    echo "Client Error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}
