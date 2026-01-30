<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Contracts\AsyncHttpClientInterface;
use JOOservices\Client\Contracts\ResponseWrapperInterface;

test('async request returns a promise resolving to response wrapper', function () {
    $mock = new MockHandler([
        new Response(200, [], '{"data": "async"}'),
    ]);
    $handler = HandlerStack::create($mock);

    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->build();

    /** @var AsyncHttpClientInterface $client */
    $promise = $client->getAsync('/test');

    expect($promise)->toBeInstanceOf(PromiseInterface::class);

    $response = $promise->wait();
    expect($response)->toBeInstanceOf(ResponseWrapperInterface::class);
    expect($response->status())->toBe(200);
    expect($response->json())->toBe(['data' => 'async']);
});

test('batch executes multiple requests concurrently', function () {
    $mock = new MockHandler([
        new Response(200, [], '{"id": 1}'),
        new Response(200, [], '{"id": 2}'),
        new Response(200, [], '{"id": 3}'),
    ]);
    $handler = HandlerStack::create($mock);

    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->build();

    /** @var AsyncHttpClientInterface $client */
    $results = $client->batch([
        'r1' => fn() => $client->getAsync('/1'),
        'r2' => fn() => $client->getAsync('/2'),
        'r3' => fn() => $client->getAsync('/3'),
    ]);

    expect($results)->toHaveCount(3);
    expect($results['r3']->json())->toBe(['id' => 3]);
});

test('batch executes Request objects concurrently', function () {
    $mock = new MockHandler([
        new Response(200, [], '{"id": 1}'),
        new Response(200, [], '{"id": 2}'),
    ]);
    $handler = HandlerStack::create($mock);

    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->build();

    /** @var AsyncHttpClientInterface $client */
    $results = $client->batch([
        'r1' => new \GuzzleHttp\Psr7\Request('GET', 'http://example.com/1'),
        'r2' => new \GuzzleHttp\Psr7\Request('GET', 'http://example.com/2'),
    ]);

    expect($results)->toHaveCount(2);
    expect($results['r1']->json())->toBe(['id' => 1]);
    expect($results['r2']->json())->toBe(['id' => 2]);
});
