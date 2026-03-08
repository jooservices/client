<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Logging\MongoDbLogger;
use MongoDB\Client as MongoClient;

test('it maps payloads through ClientBuilder + LoggingMiddleware + MongoDbLogger', function () {
    $documents = [];

    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], str_repeat('X', 20)),
    ]);

    $handler = HandlerStack::create($mock);

    $logger = new MongoDbLogger(
        maxRequestBodyBytes: 5,
        maxResponseBodyBytes: 8,
        writer: function (array $document) use (&$documents): void {
            $documents[] = $document;
        }
    );

    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->withLogger($logger, logBodies: true)
        ->build();

    $client->post('https://example.com/mongo-e2e', [
        'body' => 'request-payload-abcdef',
    ]);

    expect($documents)->toHaveCount(4);

    $requestLine = null;
    $responseLine = null;
    $responseBody = null;

    foreach ($documents as $document) {
        if (($document['message'] ?? '') === 'Sending request to POST https://example.com/mongo-e2e') {
            $requestLine = $document;
        }

        if (str_contains((string) ($document['message'] ?? ''), 'Received response 200 for POST')) {
            $responseLine = $document;
        }

        if (isset($document['response_payload'])) {
            $responseBody = $document;
        }
    }

    expect($requestLine)->not->toBeNull();
    expect($responseLine)->not->toBeNull();
    expect($responseBody)->not->toBeNull();

    expect($requestLine)->toHaveKey('method', 'POST');
    expect($requestLine)->toHaveKey('uri', 'https://example.com/mongo-e2e');

    expect($responseLine)->toHaveKey('status', 200);
    expect($responseLine)->toHaveKey('duration_ms');

    expect($responseBody)->toHaveKey('response_payload', 'XXXXXXXX');
    expect($responseBody)->toHaveKey('payload_truncated', true);
});

test('it persists MongoDbLogger output to real MongoDB when available', function () {
    $availabilityError = mongoAvailabilityError();
    if ($availabilityError !== null) {
        $this->markTestSkipped($availabilityError);
    }

    $uri = getenv('JOOCLIENT_MONGO_URI');
    if (!is_string($uri) || $uri === '') {
        $uri = 'mongodb://127.0.0.1:27017/?serverSelectionTimeoutMS=1200';
    }

    $databaseName = getenv('JOOCLIENT_MONGO_DATABASE');
    if (!is_string($databaseName) || $databaseName === '') {
        $databaseName = 'jooclient_test';
    }

    $collectionName = 'client_request_logs_' . bin2hex(random_bytes(4));
    $marker = 'mongo-real-' . bin2hex(random_bytes(6));

    $mongo = new MongoClient($uri);
    $collection = $mongo->selectCollection($databaseName, $collectionName);

    $mock = new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], str_repeat('Y', 24)),
    ]);
    $handler = HandlerStack::create($mock);

    $logger = new MongoDbLogger(
        connection: 'mongodb',
        collection: $collectionName,
        maxResponseBodyBytes: 10,
        writer: function (array $document) use ($collection): void {
            $collection->insertOne($document);
        }
    );

    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->withLogger($logger, logBodies: true)
        ->build();

    $client->get("https://example.com/{$marker}");

    $inserted = $collection->countDocuments();
    expect($inserted)->toBeGreaterThanOrEqual(3);

    $requestDoc = $collection->findOne(['message' => ['$regex' => $marker]]);
    expect($requestDoc)->not->toBeNull();

    $responseBodyDoc = $collection->findOne(['response_payload' => 'YYYYYYYYYY']);
    expect($responseBodyDoc)->not->toBeNull();
})->group('integration');
