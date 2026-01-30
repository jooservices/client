<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Resilience\RetryConfig;

test('retry feature: retries on 503 and eventually succeeds', function () {
    $mock = new MockHandler([
        new Response(503),
        new Response(503),
        new Response(200, [], '{"success": true}'),
    ]);
    $handler = HandlerStack::create($mock);

    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->withRetry(new RetryConfig(maxAttempts: 3, baseDelayMs: 1, useJitter: false))
        ->build();

    $response = $client->get('/retry-test');

    expect($response->status())->toBe(200);
    // Mock should be exhausted
    expect($mock->count())->toBe(0);
});
