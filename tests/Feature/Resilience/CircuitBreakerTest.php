<?php

declare(strict_types=1);

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\Storage\InMemoryStateStore;

test('circuit breaker feature: opens circuit after threshold', function () {
    $mock = new MockHandler([
        new RequestException("Error 1", new Request('GET', 'test')), // Fail 1
        new RequestException("Error 2", new Request('GET', 'test')), // Fail 2 -> Trip
        new Response(200), // Should not reach
    ]);
    $handler = HandlerStack::create($mock);
    $store = new InMemoryStateStore();

    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->withCircuitBreaker(new CircuitBreakerConfig(failureThreshold: 2, recoveryTimeoutMs: 1000), $store)
        ->build();

    // 1. Fail
    try {
        $client->get('/1');
    } catch (Throwable $e) {
    }
    // 2. Fail
    try {
        $client->get('/2');
    } catch (Throwable $e) {
    }

    expect($store->isCircuitOpen(2, 1000))->toBeTrue();

    // 3. Fast Fail
    try {
        $client->get('/3');
        test()->fail('Should have thrown Circuit Open Exception');
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(NetworkConnectionException::class);
        expect($e->getMessage())->toContain('Circuit Breaker is OPEN');
    }
});
