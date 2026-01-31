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
        'r1' => fn () => $client->getAsync('/1'),
        'r2' => fn () => $client->getAsync('/2'),
        'r3' => fn () => $client->getAsync('/3'),
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

test('async POST request returns a promise with posted data', function () {
    $mock = new MockHandler([
        new Response(201, [], '{"created": true, "id": 123}'),
    ]);
    $handler = HandlerStack::create($mock);

    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->build();

    /** @var AsyncHttpClientInterface $client */
    $promise = $client->postAsync('/users', [
        'json' => ['name' => 'John Doe', 'email' => 'john@example.com'],
    ]);

    expect($promise)->toBeInstanceOf(PromiseInterface::class);

    $response = $promise->wait();
    expect($response)->toBeInstanceOf(ResponseWrapperInterface::class);
    expect($response->status())->toBe(201);
    expect($response->json())->toBe(['created' => true, 'id' => 123]);
});

test('async PUT request returns a promise with updated data', function () {
    $mock = new MockHandler([
        new Response(200, [], '{"updated": true, "id": 456}'),
    ]);
    $handler = HandlerStack::create($mock);

    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->build();

    /** @var AsyncHttpClientInterface $client */
    $promise = $client->requestAsync('PUT', '/users/456', [
        'json' => ['name' => 'Jane Doe'],
    ]);

    expect($promise)->toBeInstanceOf(PromiseInterface::class);

    $response = $promise->wait();
    expect($response)->toBeInstanceOf(ResponseWrapperInterface::class);
    expect($response->status())->toBe(200);
    expect($response->json())->toBe(['updated' => true, 'id' => 456]);
});

test('async PATCH request returns a promise with partial update', function () {
    $mock = new MockHandler([
        new Response(200, [], '{"patched": true, "field": "value"}'),
    ]);
    $handler = HandlerStack::create($mock);

    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->build();

    /** @var AsyncHttpClientInterface $client */
    $promise = $client->requestAsync('PATCH', '/resources/789', [
        'json' => ['status' => 'active'],
    ]);

    expect($promise)->toBeInstanceOf(PromiseInterface::class);

    $response = $promise->wait();
    expect($response)->toBeInstanceOf(ResponseWrapperInterface::class);
    expect($response->status())->toBe(200);
    expect($response->json())->toBe(['patched' => true, 'field' => 'value']);
});

test('async DELETE request returns a promise with deletion confirmation', function () {
    $mock = new MockHandler([
        new Response(204, [], ''),
    ]);
    $handler = HandlerStack::create($mock);

    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->build();

    /** @var AsyncHttpClientInterface $client */
    $promise = $client->requestAsync('DELETE', '/resources/999');

    expect($promise)->toBeInstanceOf(PromiseInterface::class);

    $response = $promise->wait();
    expect($response)->toBeInstanceOf(ResponseWrapperInterface::class);
    expect($response->status())->toBe(204);
});

test('batch executes mixed POST and GET requests concurrently', function () {
    $mock = new MockHandler([
        new Response(200, [], '{"type": "get", "id": 1}'),
        new Response(201, [], '{"type": "post", "created": true}'),
        new Response(200, [], '{"type": "get", "id": 2}'),
    ]);
    $handler = HandlerStack::create($mock);

    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->build();

    /** @var AsyncHttpClientInterface $client */
    $results = $client->batch([
        'get1' => fn () => $client->getAsync('/item/1'),
        'post' => fn () => $client->postAsync('/items', ['json' => ['name' => 'New Item']]),
        'get2' => fn () => $client->getAsync('/item/2'),
    ]);

    expect($results)->toHaveCount(3);
    expect($results['get1']->status())->toBe(200);
    expect($results['post']->status())->toBe(201);
    expect($results['get2']->status())->toBe(200);
});
