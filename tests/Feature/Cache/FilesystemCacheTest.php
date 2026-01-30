<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Cache\FilesystemCache;
use JOOservices\Client\Client\ClientBuilder;

beforeEach(function () {
    clearTmpDir();
});

test('it caches responses to disk and serves valid cache on hit', function () {
    // 1. Arrange
    $mock = new MockHandler([
        new Response(200, [], '{"data": "fresh"}'),
        new Response(200, [], '{"data": "fresh_2"}'), // Should NOT be reached if cached
    ]);

    $handler = HandlerStack::create($mock);
    $cacheDir = makeTmpDir('cache');

    // 2. Build Client
    $cache = new FilesystemCache($cacheDir);
    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->withCache($cache, defaultTtl: 3600)
        ->build();

    // 3. First Call (Miss -> Network)
    $response1 = $client->get('/api/data');
    expect((string) $response1->toPsrResponse()->getBody())->toBe('{"data": "fresh"}');

    // Verify file created
    // Logic: FilesystemCache uses md5(key) as filename.
    // Key logic in Middleware: "GET https://.../api/data" -> md5
    // But we don't need to know internal naming if we verify side effect: directory not empty.
    $files = scandir($cacheDir);
    $cachedFiles = array_diff($files, ['.', '..']);
    expect($cachedFiles)->not->toBeEmpty();

    // 4. Second Call (Hit -> Cache)
    // If it hits network, mock returns "fresh_2". If cache, "fresh".
    $response2 = $client->get('/api/data');
    expect((string) $response2->toPsrResponse()->getBody())->toBe('{"data": "fresh"}');

    // Verify Mock was only called once (MockHandler throws if called when empty? Or we check count)
    // MockHandler moves pointer. If we consumed 2nd response, getLastRequest would reflect it?
    // Better: assert response body content is from first call.
});

test('it respects TTL and expires cache', function () {
    // We can't wait 3600s. We use 1s TTL and sleep 2s.

    $mock = new MockHandler([
        new Response(200, [], '{"data": "v1"}'),
        new Response(200, [], '{"data": "v2"}'),
    ]);
    $handler = HandlerStack::create($mock);
    $cacheDir = makeTmpDir('cache_ttl');

    $cache = new FilesystemCache($cacheDir);
    $client = ClientBuilder::create()
        ->withOption('handler', $handler)
        ->withCache($cache, defaultTtl: 1) // 1 second
        ->build();

    // Call 1
    $client->get('/api/expire');

    // Sleep 2s
    sleep(2);

    // Call 2 -> Should miss cache
    $response = $client->get('/api/expire');
    expect((string) $response->toPsrResponse()->getBody())->toBe('{"data": "v2"}');
});
