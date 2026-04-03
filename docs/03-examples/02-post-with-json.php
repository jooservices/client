<?php

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use JOOservices\Client\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->build();

echo "Creating a new post...\n";

$response = $client->post('/posts', [
    'json' => [
        'title' => 'foo',
        'body' => 'bar',
        'userId' => 1,
    ],
    'headers' => [
        'X-Custom-Header' => 'example'
    ]
]);

if ($response->status() >= 200 && $response->status() < 300) {
    $data = $response->json();
    echo "Created Post ID: " . $data['id'] . "\n";
    echo "Full Response: " . $response->toPsrResponse()->getBody() . "\n";
} else {
    echo "Failed to create post. Status: " . $response->status() . "\n";
}
